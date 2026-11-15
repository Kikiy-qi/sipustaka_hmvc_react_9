<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Peminjaman\Models\Peminjaman;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

function dataPeminjaman(array $overrides = []): array
{
    return [
        'nama_peminjam' => 'Budi Santoso',
        'judul_buku' => 'Pemrograman Web Modern',
        'tanggal_pinjam' => '2026-11-10',
        'tanggal_kembali' => '2026-11-17',
        'status' => 'Dipinjam',
        ...$overrides,
    ];
}

test('index renders the react page of the peminjaman module', function () {
    $this->get('/peminjaman')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Peminjaman::index', false));
});

test('data endpoint returns loans as json', function () {
    Peminjaman::factory()->count(3)->create();

    $this->getJson('/peminjaman/data')->assertOk()->assertJsonCount(3);
});

test('store returns 422 when the form is empty', function () {
    $this->postJson('/peminjaman', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'nama_peminjam',
            'judul_buku',
            'tanggal_pinjam',
            'tanggal_kembali',
            'status',
            'berkas',
        ]);
});

test('store saves the loan data and the uploaded file', function () {
    $this->postJson('/peminjaman', dataPeminjaman([
        'berkas' => UploadedFile::fake()->create('bukti_pinjam.pdf', 100, 'application/pdf'),
    ]))->assertOk()->assertJson(['success' => 'Data peminjaman berhasil disimpan.']);

    $peminjaman = Peminjaman::first();

    expect($peminjaman->nama_berkas)->toBe('bukti_pinjam.pdf');
    Storage::disk('public')->assertExists($peminjaman->berkas);
});

test('zip file is rejected', function () {
    $this->postJson('/peminjaman', dataPeminjaman([
        'berkas' => UploadedFile::fake()->create('bukti.zip', 100),
    ]))->assertStatus(422)->assertJsonValidationErrors('berkas');
});

test('update replaces the old file and updates status', function () {
    $this->postJson('/peminjaman', dataPeminjaman([
        'berkas' => UploadedFile::fake()->create('lama.pdf', 100, 'application/pdf'),
    ]));

    $peminjaman = Peminjaman::first();
    $oldPath = $peminjaman->berkas;

    $this->postJson("/peminjaman/{$peminjaman->id}", dataPeminjaman([
        '_method' => 'PUT',
        'status' => 'Dikembalikan',
        'berkas' => UploadedFile::fake()->create('bukti_kembali.pdf', 100, 'application/pdf'),
    ]))->assertOk();

    Storage::disk('public')->assertMissing($oldPath);
    expect($peminjaman->fresh()->status)->toBe('Dikembalikan');
});

test('destroy deletes the data and its file, unknown id returns 404', function () {
    $this->postJson('/peminjaman', dataPeminjaman([
        'berkas' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
    ]));

    $peminjaman = Peminjaman::first();

    $this->deleteJson("/peminjaman/{$peminjaman->id}")->assertOk();

    Storage::disk('public')->assertMissing($peminjaman->berkas);
    $this->assertDatabaseMissing('peminjamans', ['id' => $peminjaman->id]);
    $this->deleteJson('/peminjaman/999')->assertNotFound();
});
