<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function stored(string $name = 'hardcover-20261009-101500.dump'): string
    {
        Storage::disk('local')->put('backups/'.$name, 'PGDMP-fake');

        return $name;
    }

    public function test_only_super_admin_sees_backups(): void
    {
        $name = $this->stored();

        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get(route('super.backups.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super.backups.download', $name))->assertForbidden();
            $this->actingAs($user)->delete(route('super.backups.destroy', $name))->assertForbidden();
        }
    }

    public function test_super_admin_lists_downloads_and_deletes(): void
    {
        $name = $this->stored();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('super.backups.index'))->assertOk()->assertSee($name);
        $this->actingAs($super)->get(route('super.backups.download', $name))->assertOk()->assertDownload($name);
        $this->actingAs($super)->delete(route('super.backups.destroy', $name))->assertRedirect();

        Storage::disk('local')->assertMissing('backups/'.$name);
        $this->assertDatabaseHas('activity_logs', ['action' => 'backup.deleted']);
    }

    public function test_names_that_are_not_backups_are_refused(): void
    {
        Storage::disk('local')->put('secreto.txt', 'x');
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('super.backups.download', 'secreto.txt'))->assertNotFound();
        $this->actingAs($super)->get(route('super.backups.download', '..%2F..%2F.env'))->assertNotFound();
        $this->assertNull(app(DatabaseBackup::class)->path('../.env'));
        $this->assertNull(app(DatabaseBackup::class)->path('hardcover-20261009-101500.dump'));
    }

    public function test_creating_a_backup_with_pg_dump(): void
    {
        if (! (new \Symfony\Component\Process\ExecutableFinder)->find('pg_dump')) {
            $this->markTestSkipped('pg_dump no está instalado.');
        }

        $name = app(DatabaseBackup::class)->create();

        $this->assertMatchesRegularExpression('/^hardcover-\d{8}-\d{6}\.dump$/', $name);
        $this->assertGreaterThan(0, Storage::disk('local')->size('backups/'.$name));
        $this->assertSame($name, app(DatabaseBackup::class)->all()[0]['name']);
    }
}
