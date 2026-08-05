<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Http;

use App\Models\User;
use Atlas\Modules\Identity\Application\PrincipalFactory;
use Atlas\Modules\Identity\Domain\Principal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class SessionController
{
    /**
     * Provides the CSRF token bound to the browser session before a login
     * command. The token does not authenticate or grant permissions.
     */
    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['csrf_token' => $request->session()->token()]);
    }

    public function store(Request $request, PrincipalFactory $principals): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => ['Credenciais inválidas.']]);
        }
        $request->session()->regenerate();
        $user = $request->user();
        if (! $user instanceof User) {
            throw new \LogicException('A sessão não possui um usuário humano.');
        }
        $principal = $principals->forUser($user);

        return response()->json(['principal' => $this->present($principal)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(status: 204);
    }

    /**
     * @param  Principal  $principal
     * @return array{id:string,type:string,name:string,avatar_initials:string}
     */
    private function present($principal): array
    {
        return ['id' => $principal->id, 'type' => $principal->type, 'name' => $principal->displayName, 'avatar_initials' => $principal->avatarInitials];
    }
}
