<?php

namespace App\Services;

use App\Models\EnrollmentLessonQuota;

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
        
        // Verificar se há quotas por categoria definidas
        $quotaModel = new EnrollmentLessonQuota();
        $quotas = $quotaModel->findByEnrollment($enrollment['id']);
        
        if (!empty($quotas)) {
            // Sistema novo: tem quotas por categoria
            // Pode agendar se houver pelo menos uma categoria com saldo
            $totalContracted = $quotaModel->getTotalByEnrollment($enrollment['id']);
            return $totalContracted > 0;
        } else {
            // Sistema antigo: fallback para aulas_contratadas
            $aulasContratadas = $enrollment['aulas_contratadas'] ?? null;
            if ($aulasContratadas === null || (int)$aulasContratadas <= 0) {
                return false;
            }
            return true;
        }
    }

    public static function canScheduleCategory($enrollment, $categoryId, $lessonCount = 1)
    {
        if (!$enrollment) {
            return ['can_schedule' => false, 'reason' => 'Matrícula inválida'];
        }
        
        if ($enrollment['financial_status'] === 'bloqueado') {
            return ['can_schedule' => false, 'reason' => 'Situação financeira bloqueada'];
        }
        
        $quotaModel = new EnrollmentLessonQuota();
        $quota = $quotaModel->getQuotaWithScheduledCount($enrollment['id'], $categoryId);
        
        if (!$quota) {
            return ['can_schedule' => false, 'reason' => 'Categoria não contratada nesta matrícula'];
        }
        
        $remaining = (int)($quota['remaining'] ?? 0);
        
        if ($remaining < $lessonCount) {
            return [
                'can_schedule' => false, 
                'reason' => "Saldo insuficiente. Contratadas: {$quota['contracted']}, agendadas: {$quota['scheduled']}, restantes: {$remaining}"
            ];
        }
        
        return ['can_schedule' => true, 'remaining' => $remaining];
    }

    public static function canStartLesson($enrollment)
    {
        if (!$enrollment) {
            return false;
        }
        
        return $enrollment['financial_status'] !== 'bloqueado';
    }

    public static function hasQuotasByCategory($enrollmentId)
    {
        $quotaModel = new EnrollmentLessonQuota();
        $quotas = $quotaModel->findByEnrollment($enrollmentId);
        return !empty($quotas);
    }
}
