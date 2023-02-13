<?php

namespace App\Entity;

use App\Repository\FileArchiveRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FileArchiveRepository::class)]
class FileArchive
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\Column(nullable: true)]
    private ?bool $is_download = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getIsDownload(): ?bool
    {
        return $this->is_download;
    }

    public function setIsDownload(?bool $is_download): self
    {
        $this->is_download = $is_download;

        return $this;
    }
}
