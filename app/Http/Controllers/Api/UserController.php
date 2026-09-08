<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * GET /api/users — daftar semua akun admin.
     */
    public function index(Request $request)
    {
        $query = User::query()->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->get()->map(fn(User $user) => $this->formatUser($user));

        return response()->json([
            'data' => $users,
            'total' => $users->count(),
        ]);
    }

    /**
     * POST /api/users — tambah akun baru.
     * Role 'admin' sudah tidak dipakai lagi. Setiap akun baru langsung
     * dapat kedua role sekaligus: super-admin + admin-instansi (levelnya
     * sama, tidak dibedakan dari instansi_id).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'instansi_id' => ['nullable', 'exists:instansi,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'instansi_id' => $validated['instansi_id'] ?? null,
        ]);

        $user->syncRoles(['super-admin', 'admin-instansi']);

        return response()->json([
            'message' => 'Akun berhasil ditambahkan.',
            'data' => $this->formatUser($user),
        ], 201);
    }

    /**
     * PUT/PATCH /api/users/{user} — ubah data akun.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'instansi_id' => ['nullable', 'exists:instansi,id'],
        ]);

        $user->fill([
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'instansi_id' => $validated['instansi_id'] ?? $user->instansi_id,
        ]);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'Akun berhasil diperbarui.',
            'data' => $this->formatUser($user),
        ]);
    }

    /**
     * DELETE /api/users/{user} — hapus akun.
     */
    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Tidak bisa menghapus akun sendiri.',
            ], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Akun berhasil dihapus.',
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'instansi_id' => $user->instansi_id,
        ];
    }
}
