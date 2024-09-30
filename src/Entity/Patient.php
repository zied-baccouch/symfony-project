<?php
namespace App\Entity;
use App\Repository\PatientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Table(name: 'patient')]
#[ORM\Index(name: 'FK_PATIENT_APP01_MEDECIN', columns: ['medecin_id'])]
#[ORM\Entity(repositoryClass: PatientRepository::class)]
class Patient
{
    #[ORM\Column(name: "id_patient")]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $idPatient = null;

    #[ORM\Column(name: "medecin_id")]
    private ?int $medecinId = null;

    #[ORM\Column(name: "nom_prenom", length: 254)]
    private ?string $nomPrenom = null;

    #[ORM\Column(name: "date_naiss", type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateNaiss = null;

    #[ORM\Column(name: "genre", length: 20, nullable: true)]
    private ?string $genre = 'NULL';

    #[ORM\Column(name: "date_entree", type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateEntree = null;
    public function getIdPatient(): ?int
    {
        return $this->idPatient;
    }

    public function getMedecinId(): ?int
    {
        return $this->medecinId;
    }

    public function setMedecinId(int $medecinId): static
    {
        $this->medecinId = $medecinId;

        return $this;
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

    public function getDateNaiss(): ?\DateTimeInterface
    {
        return $this->dateNaiss;
    }

    public function setDateNaiss(?\DateTimeInterface $dateNaiss): static
    {
        $this->dateNaiss = $dateNaiss;

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(?string $genre): static
    {
        $this->genre = $genre;

        return $this;
    }

    public function getDateEntree(): ?\DateTimeInterface
    {
        return $this->dateEntree;
    }

    public function setDateEntree(?\DateTimeInterface $dateEntree): static
    {
        $this->dateEntree = $dateEntree;

        return $this;
    }
}