<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['name' => 'Básico'],
            [
                'description' => 'Plano ideal para profissionais autônomos. Inclui acesso ao painel de controle, 1 usuário e suporte por e-mail.',
                'price' => 29.90,
                'active' => true,
            ]
        );

        Plan::updateOrCreate(
            ['name' => 'Profissional'],
            [
                'description' => 'Perfeito para pequenas equipes. Inclui até 10 usuários, relatórios avançados e suporte prioritário.',
                'price' => 79.90,
                'active' => true,
            ]
        );

        Plan::updateOrCreate(
            ['name' => 'Empresarial'],
            [
                'description' => 'Para empresas que precisam de escala. Usuários ilimitados, API dedicada, gerente de conta e SLA garantido.',
                'price' => 199.90,
                'active' => true,
            ]
        );
    }
}
