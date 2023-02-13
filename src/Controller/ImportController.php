<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Message\ImportJob;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\Extension\Core\Type\SubmitType; 
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Entity\Negotiation;
use App\Entity\FileArchive;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ImportController extends AbstractController
{
    #[Route('/import', name: 'app_import')]
    public function index(MessageBusInterface $bus, ManagerRegistry $doctrine): Response
    {
        $request = Request::createFromGlobals();
        $form = $this->createFormBuilder()
            ->add('fileType', ChoiceType::class, ['choices'  => [
                'csv' => 'csv',
                'xls' => 'xls',
                'xlsx' => 'xlsx'
            ]
        ])
            ->add('file', FileType::class, [
                'label' => 'Upload',
                'required' => true,
            ])
            ->add('Submit', SubmitType::class, ['label' => 'Save'])
            ->getForm();

            $em = $this->getDoctrine()->getManager();
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
            $session = new Session();
            $session->getFlashBag()->add('success', 'The data is being processed. Please wait.');
            $records = $doctrine->getRepository(Negotiation::class)->findBy([
                'is_published' => NULL
            ]);
            if($records){
                $session = new Session();
                $session->getFlashBag()->add('error', 'The data is still being processed');
                return $this->render('import.html.twig', array(
                    'uploadForm' => $form->createView(),
                ));
            }

            $file = $form->get('file')->getData();
            $fileType = $form->get('fileType')->getData();
            if (!in_array($file->getClientOriginalExtension(), ['csv', 'xls', 'xlsx'])) {
                $message = 'Upload file with the selected extension!';
                return $this->render('import.html.twig', array(
                    'uploadForm' => $form->createView(),
                    'message' => $message,
                ));
            }

            if (!isset($file)) {
                $message = 'Please choose a file';
                return $this->render('import.html.twig', array(
                    'uploadForm' => $form->createView(),
                    'message' => $message,
                ));
            }

            $date = time();

            $file->move('./uploads', "imported_$date.". $fileType);

            $inputFileName = "./uploads/imported_$date.". $fileType;
            $spreadsheet = IOFactory::load($inputFileName);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            
            $bus->dispatch(new ImportJob(json_encode($sheetData), $em, $doctrine));
            $session = new Session();
            $session->getFlashBag()->add('success', 'The result is ready!');
    
            $fileArchive = $doctrine->getRepository(FileArchive::class)->findOneBy([
                'is_download'   => NULL
            ]);
            $fileArchive->setIsDownload(1);
            $em->persist($fileArchive);
            $em->flush();      
            $response = new BinaryFileResponse($fileArchive->getPath());
            $response->headers->set ( 'Content-Type', 'text/plain' );
            $response->setContentDisposition ( ResponseHeaderBag::DISPOSITION_ATTACHMENT, "result_".time(). ".txt" );
            return $response;
        }
        return $this->render('import.html.twig', array(
                    'uploadForm' => $form->createView(),
                ));
    }
}
