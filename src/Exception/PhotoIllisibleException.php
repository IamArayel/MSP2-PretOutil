<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * La photo envoyée a passé la validation (type, taille) mais GD ne parvient pas à la décoder :
 * fichier tronqué ou contenu falsifié.
 */
final class PhotoIllisibleException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cette photo ne peut pas être lue. Essayez avec un autre fichier JPEG, PNG ou WebP.');
    }
}
