<?php

namespace App\Entity;

use App\Repository\DriverHistoryEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DriverHistoryEntryRepository::class)]
class DriverHistoryEntry extends AbstractDriverEvent
{
}