<?php

declare(strict_types=1);

namespace Millacoco\Admin\Models;

use PDO;

class DocumentType
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Validates that the provided document_type_id exists in the document_types table
     */
    public function isValidType(int $documentTypeId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM document_types WHERE id = :id');
        $stmt->execute(['id' => $documentTypeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Fetches all registered document types
     */
    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT id, name, code, description FROM document_types ORDER BY name ASC');
        return $stmt->fetchAll();
    }
}
