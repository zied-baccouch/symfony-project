<?php
namespace App\Entity;
use App\Repository\MedecinRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Table(name: 'medecin')]
#[ORM\Entity(repositoryClass: MedecinRepository::class)]
class Medecin
{
    #[ORM\Column(name: "id_medecin")]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $idMedecin = null;

    #[ORM\Column(name: "nom_prenom", length: 254)]
    private ?string $nomPrenom = null;

    #[ORM\Column(name: "code", length: 254, nullable: true)]
    private ?string $code = 'NULL';
    public function getIdMedecin(): ?int
    {
        return $this->idMedecin;
    }

    public function getNomPrenom(): ?string
    {
        return $this->nomPrenom;
    }

    public function setNomPrenom(string $nomPrenom): static
    {
        $this->nomPrenom = $nomPrenom;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }
}