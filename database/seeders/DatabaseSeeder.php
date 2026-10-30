<?php

namespace Database\Seeders;

use App\Enums\Permission as AppPermission;
use App\Enums\Role as AppRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (AppPermission::values() as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::firstOrCreate([
            'name' => AppRole::Admin->value,
            'guard_name' => 'web',
        ]);

        $clientRole = Role::firstOrCreate([
            'name' => AppRole::Client->value,
            'guard_name' => 'web',
        ]);

        $adminRole->syncPermissions(AppPermission::adminDefaults());
        $clientRole->syncPermissions(AppPermission::clientDefaults());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles([AppRole::Client->value]);

        $catalog = [
            [
                'name' => 'Landing Page de Alta Conversão',
                'description' => 'Desenvolvimento completo de Landing Page responsiva, otimizada para SEO e performance.',
                'base_price' => 1200.00,
                'billing_type' => 'one_time',
                'billing_interval' => null,
            ],
            [
                'name' => 'Sistema Web / SaaS Sob Medida',
                'description' => 'Aplicação full-stack com painel administrativo, autenticação segura e banco de dados.',
                'base_price' => 4500.00,
                'billing_type' => 'one_time',
                'billing_interval' => null,
            ],
            [
                'name' => 'Prototipagem UI/UX no Figma',
                'description' => 'Design system, identidade de interface e telas interativas desktop e mobile no Figma.',
                'base_price' => 850.00,
                'billing_type' => 'one_time',
                'billing_interval' => null,
            ],
            [
                'name' => 'Hospedagem WeBest Cloud (Máquina Virtual)',
                'description' => 'Infraestrutura dedicada em nuvem com deploy automatizado, backups e SSL gerenciado.',
                'base_price' => 49.90,
                'billing_type' => 'recurring',
                'billing_interval' => 'month',
            ],
            [
                'name' => 'Gestão de Domínio & DNS Seguro',
                'description' => 'Configuração anual de domínio próprio, apontamentos DNS, proteção Cloudflare e renovação.',
                'base_price' => 89.90,
                'billing_type' => 'recurring',
                'billing_interval' => 'year',
            ],
        ];

        foreach ($catalog as $item) {
            Product::query()->updateOrCreate(
                ['name' => $item['name']],
                array_merge($item, ['active' => true]),
            );
        }
    }
}
