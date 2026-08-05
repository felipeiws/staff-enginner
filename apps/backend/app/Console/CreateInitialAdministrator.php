<?php

declare(strict_types=1);

namespace App\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Bootstrap-only command; authorization remains scoped even for platform administrators. */
final class CreateInitialAdministrator extends Command
{
    protected $signature = 'atlas:create-initial-admin
        {--name= : Nome exibido do administrador (ou ATLAS_ADMIN_NAME)}
        {--email= : E-mail do administrador (ou ATLAS_ADMIN_EMAIL)}
        {--organization= : Escopo da organização (ou ATLAS_ADMIN_ORGANIZATION)}';

    protected $description = 'Cria ou atualiza o primeiro administrador de plataforma a partir de variáveis de ambiente.';

    public function __construct(private readonly DatabaseManager $database)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: env('ATLAS_ADMIN_NAME'));
        $email = (string) ($this->option('email') ?: env('ATLAS_ADMIN_EMAIL'));
        $organization = (string) ($this->option('organization') ?: env('ATLAS_ADMIN_ORGANIZATION', 'default'));
        $password = (string) env('ATLAS_ADMIN_PASSWORD');

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 16 || $organization === '') {
            $this->error('Defina ATLAS_ADMIN_NAME, ATLAS_ADMIN_EMAIL, ATLAS_ADMIN_PASSWORD (mínimo 16 caracteres) e ATLAS_ADMIN_ORGANIZATION.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = Hash::make($password);
        $user->save();

        $scope = json_encode(['organization' => $organization], JSON_THROW_ON_ERROR);
        $this->database->table('identity_grants')->updateOrInsert(
            ['user_id' => $user->getKey(), 'scope' => $scope],
            [
                'id' => (string) Str::uuid(),
                'role' => 'administrador_de_plataforma',
                'permissions' => json_encode(['*'], JSON_THROW_ON_ERROR),
                'classification_ceiling' => 'confidencial',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->info("Administrador inicial pronto para {$email} no escopo {$organization}.");

        return self::SUCCESS;
    }
}
