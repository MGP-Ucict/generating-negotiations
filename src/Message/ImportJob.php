<?php
namespace App\Message;

class ImportJob
{
    private $content;
    private $em;
    private $doctrine;

    public function __construct(string $content, $em, $doctrine)
    {
        $this->content = $content;
        $this->em = $em;
        $this->doctrine = $doctrine;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getEm()
    {
        return $this->em;
    }

    public function getDoctrine()
    {
        return $this->doctrine;
    }
}