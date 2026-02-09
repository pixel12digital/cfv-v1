<?php

namespace App\Services;

class EnrollmentPolicy
{
    public static function canSchedule($enrollment)
    {
        if (!$enrollment) {
            return false;
        }
        if ($enrollment['financial_status'] === 'bloqueado') {
            return false;
        }
        // Sem aulas contratadas = não pode agendar (deve definir quantidade na matrícula)
        $aulasContratadas = $enrollment['aulas_contratadas'] ?? null;
        if ($aulasContratadas === null || (int)$aulasContratadas <= 0) {
            return false;
        }
        return true;
    }

    public static function canStartLesson($enrollment)
    {
        if (!$enrollment) {
            return false;
        }
        
        return $enrollment['financial_status'] !== 'bloqueado';
    }
}
