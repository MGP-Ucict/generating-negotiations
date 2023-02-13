<?php

namespace App\MessageHandler;

use App\Message\ImportJob;
use App\Entity\Item;
use App\Entity\Offer;
use App\Entity\Negotiation;
use App\Entity\FileArchive;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Filesystem\Filesystem;

#[AsMessageHandler]
class ImportJobHandler
{
    public function __invoke(ImportJob $data)
    {
        $array = json_decode($data->getContent());
        $p = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L','M', 'N','O', 'P', 'Q', 'R', 
        'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z','AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 
        'AL','AM', 'AN','AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ'];
        $rowCounter = 0;
        $header = [];
        foreach($array as $row) {
            $columnCounter = 0;
            if (!$rowCounter) {
                $header = $row;
            }
            $item = null;
            foreach($row as $cell) {
                if (!is_null($cell)) {
                    if (!$columnCounter && $rowCounter) {
                        $item = new Item();
                        $item->setName($cell);
                        $data->getEm()->persist($item);
                        $data->getEm()->flush();
                    } else if (!is_null($item)){
                        $offer = new Offer();
                        $offer->setItemId($item->getId());
                        $price = is_numeric($cell)? $cell: NULL;
                        $offer->setPrice($price);
                        $index = $p[$columnCounter];
                        $headerArray = get_object_vars($header);
                        $offer->setName($headerArray[$index]);
                        $data->getEm()->persist($offer);
                        $data->getEm()->flush();
                    }
                   
                }
                $columnCounter++;
            }
            $rowCounter++;
        }


        // apply the algorithm for negotiations
        $this->generateNegotiations($data->getEm(), $data->getDoctrine());


    }

    protected function sortItems($items, $doctrine)
    {
        $fullDataItem = [];
        $notFullDataItem = [];
        foreach ($items as $item)
        {
            $itemId = $item->getId();
            $allAvailableOffersForItem = $doctrine->getRepository(Offer::class)->findByItemId($itemId);
            $allUnavailableOffersForItem = $doctrine->getRepository(Offer::class)->findBy([
                'item_id' => $itemId,
                'price'   => NULL
            ]);
            if (!count($allUnavailableOffersForItem)){
                $fullDataItem[] = $item;
            } else {
                $notFullDataItem[] = $item;
            }
        }
        $sortedItems = array_merge($fullDataItem, $notFullDataItem);
        return $sortedItems;

    }


    protected function generateNegotiations($em, $doctrine) 
    {
        // $filesystem = new Filesystem();
        $fileName = 'exports/results_'.time().'.txt';
        $offerRepository = $doctrine->getRepository(Offer::class);
        $items = $doctrine->getRepository(Item::class)->findBy([
                'is_published' => NULL
            ]);
        $negotiationCounter = 0;
        $negotiations = [];
        $hasUnavailableBefore = false;
        $i = 0;
        $sortedItems = $this->sortItems($items, $doctrine);
        foreach ($sortedItems as $item)
        {
            $itemId = $item->getId();
            $allAvailableOffersForItem = $doctrine->getRepository(Offer::class)->findByItemId($itemId);
            $allUnavailableOffersForItem = $doctrine->getRepository(Offer::class)->findBy([
                'item_id' => $itemId,
                'price'   => NULL
            ]);
            if (!count($allUnavailableOffersForItem)){
                if (empty($negotiations) || $hasUnavailableBefore) {
                    $negotiations[$negotiationCounter] = $this->createNegotiation($negotiationCounter, $em);
                    $this->publishNegotiation($negotiations[$negotiationCounter], $em);
                    file_put_contents($fileName, "\r\n\n". $negotiations[$negotiationCounter]->getName() . "\r\n", FILE_APPEND);
                    $this->setOffersNegotiationId($em, $item, $allAvailableOffersForItem, $negotiations[$negotiationCounter], $fileName);
                    if ($hasUnavailableBefore && !$i) {
                        $negotiationCounter++;
                    }
                } 
                else if (!$hasUnavailableBefore && !empty($negotiations)) {
                   //dd(123, $negotiationCounter, $negotiations);
                    $this->setOffersNegotiationId($em, $item, $allAvailableOffersForItem, $negotiations[$negotiationCounter], $fileName);
                }
            } 
            else {//(count($allUnavailableOffersForItem)) {
                $hasUnavailableBefore = true;

                if ($negotiationCounter) {
                    $this->publishNegotiation($negotiations[$negotiationCounter - 1], $em);
                }
                $negotiationIndex = (empty($negotiations)) ? $negotiationCounter : $negotiationCounter + 1;
                $negotiations[$negotiationCounter] = $this->createNegotiation($negotiationIndex, $em);
                file_put_contents($fileName, "\r\n\n". $negotiations[$negotiationCounter]->getName() . "\r\n", FILE_APPEND);
                $this->setOffersNegotiationId($em, $item, $allAvailableOffersForItem, $negotiations[$negotiationCounter], $fileName);
                $negotiationCounter++;
            }
            $item->setIsPublished(1);
            $em->persist($item);
            $em->flush();
            $i++;         
        }
        $this->publishNegotiation($negotiations[$negotiationCounter-1], $em);

        $fileArchive = new FileArchive();
        $fileArchive->setPath($fileName);
        $em->persist($fileArchive);
        $em->flush();        

    }

    protected function publishNegotiation($negotiation, $em)
    {
        $negotiation->setIsPublished(1);
        $em->persist($negotiation);
        $em->flush();        
    }

    protected function createNegotiation($negotiationCounter, $em)
    {
        $negotiationNew = new Negotiation();
        $negotiationNew->setName("Negotiation" . $negotiationCounter + 1);
        $em->persist($negotiationNew);
        $em->flush();
        return $negotiationNew;
    }
    protected function setOffersNegotiationId($em, $item, $allAvailableOffersForItem, $negotiation, $fileName)
    {
         file_put_contents($fileName, "\r\n" . ' Item: '. $item->getName() .':', FILE_APPEND);
        foreach ($allAvailableOffersForItem as $offer) {
            $offer->setNegotiationId($negotiation->getId());
            $em->persist($offer);
            $em->flush();
            file_put_contents($fileName, '|Offer: '. $offer->getName() .' , price: '. $offer->getPrice(), FILE_APPEND);
        }
    }
}