<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Buku\Models\Buku;

test('user and admin can view book catalog', function () {
    Buku::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get('/buku')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Buku::index', false)
            ->has('bukus.data', 3));
});

test('regular user cannot create, edit, or delete books', function () {
    $user = User::factory()->create();
    $buku = Buku::factory()->create();

    $this->actingAs($user)->get('/buku/create')->assertForbidden();
    $this->actingAs($user)->post('/buku', [])->assertForbidden();
    $this->actingAs($user)->get("/buku/{$buku->id}/edit")->assertForbidden();
    $this->actingAs($user)->put("/buku/{$buku->id}", [])->assertForbidden();
    $this->actingAs($user)->delete("/buku/{$buku->id}")->assertForbidden();
});

test('admin can create, update, and delete a book', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/buku', [
            'judul' => 'Pemrograman Web Modern',
            'penulis' => 'Andi Nugroho',
            'penerbit' => 'Informatika Bandung',
            'tahun_terbit' => 2025,
            'stok' => 12,
        ])
        ->assertRedirect('/buku')
        ->assertSessionHas('success');

    $buku = Buku::first();
    expect($buku)->not->toBeNull();
    expect($buku->judul)->toBe('Pemrograman Web Modern');

    $this->actingAs($admin)
        ->put("/buku/{$buku->id}", [
            'judul' => 'Pemrograman Web Modern Edisi 2',
            'penulis' => 'Andi Nugroho',
            'penerbit' => 'Informatika Bandung',
            'tahun_terbit' => 2026,
            'stok' => 8,
        ])
        ->assertRedirect('/buku')
        ->assertSessionHas('success');

    expect($buku->fresh()->stok)->toBe(8);

    $this->actingAs($admin)
        ->delete("/buku/{$buku->id}")
        ->assertRedirect('/buku')
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('bukus', ['id' => $buku->id]);
});
