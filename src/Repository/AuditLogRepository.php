<?php

declare(strict_types=1);

namespace App\Repository;

use App\Document\AuditLog;
use App\Enum\ActionAudit;
use App\Pagination\Page;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\Bundle\MongoDBBundle\Repository\ServiceDocumentRepository;

/**
 * Lecture du journal d'audit (US10, écran E9), collection MongoDB audit_log.
 *
 * Injection NoSQL : le filtre reçoit une ActionAudit (énumération), jamais une valeur
 * saisie brute ; aucun opérateur MongoDB ($ne, $gt, $where...) ne peut venir de la requête HTTP.
 *
 * @extends ServiceDocumentRepository<AuditLog>
 */
class AuditLogRepository extends ServiceDocumentRepository
{
    public const int ENTREES_PAR_PAGE = 20;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Du plus récent au plus ancien, filtré par type d'action si demandé.
     * Tri et filtre servis par les index ttl_horodatage_1an et idx_action_horodatage.
     *
     * @return Page<AuditLog>
     */
    public function lister(?ActionAudit $action, int $numeroPage = 1): Page
    {
        $numeroPage = max(1, $numeroPage);

        $requete = $this->createQueryBuilder();
        if (null !== $action) {
            $requete->field('action')->equals($action->value);
        }

        $total = (clone $requete)->count()->getQuery()->execute();

        /** @var list<AuditLog> $entrees */
        $entrees = $requete
            ->sort('horodatage', 'desc')
            ->skip(($numeroPage - 1) * self::ENTREES_PAR_PAGE)
            ->limit(self::ENTREES_PAR_PAGE)
            ->getQuery()
            ->toArray();

        return new Page(array_values($entrees), (int) $total, $numeroPage, self::ENTREES_PAR_PAGE);
    }
}
