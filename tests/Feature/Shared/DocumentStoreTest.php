<?php

use App\Domain\Documents\DocumentStore;
use App\Domain\Shared\DomainRuleViolation;
use App\Enums\DocumentClassification;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedRoles();
    Storage::fake('local');
    $this->agency = Agency::factory()->create();
    $this->author = User::factory()->forAgency($this->agency)->create();
    $this->store = app(DocumentStore::class);
    $this->document = fn (DocumentClassification $c = DocumentClassification::Internal) => Document::factory()->create([
        'agency_id' => $this->agency->id, 'created_by' => $this->author->id, 'classification' => $c,
    ]);
    $this->pdf = fn (string $content = '%PDF-1.4 test') => UploadedFile::fake()->createWithContent('memo.pdf', $content);
});

it('stores hashed, numbered versions without overwriting', function () {
    $doc = ($this->document)();
    $v1 = $this->store->addVersion($doc, ($this->pdf)('%PDF-1.4 first'), $this->author);
    $v2 = $this->store->addVersion($doc, ($this->pdf)('%PDF-1.4 second'), $this->author);

    expect([$v1->version, $v2->version])->toBe([1, 2])
        ->and($v1->sha256)->toBe(hash('sha256', '%PDF-1.4 first'))
        ->and($v1->storage_key)->not->toBe($v2->storage_key)
        ->and(Storage::disk('local')->get($v1->storage_key))->toBe('%PDF-1.4 first')
        ->and($this->store->verify($v1))->toBeTrue();
});

it('detects tampered content', function () {
    $version = $this->store->addVersion(($this->document)(), ($this->pdf)(), $this->author);
    Storage::disk('local')->put($version->storage_key, 'altered');

    expect($this->store->verify($version))->toBeFalse();
});

it('rejects disallowed file types', function () {
    $this->store->addVersion(($this->document)(), UploadedFile::fake()->createWithContent('run.sh', "#!/bin/sh\necho hi"), $this->author);
})->throws(DomainRuleViolation::class, 'not accepted');

it('serves downloads only through signed URLs after a policy check', function () {
    $version = $this->store->addVersion(($this->document)(), ($this->pdf)(), $this->author);
    $colleague = User::factory()->forAgency($this->agency)->create();

    $this->actingAs($colleague)->get(route('documents.download', $version))->assertForbidden(); // unsigned
    $this->actingAs($colleague)->get($this->store->temporaryUrl($version))->assertOk()->assertDownload('memo.pdf');

    expect(AuditLog::where('event', 'document.downloaded')->exists())->toBeTrue();
});

it('expires download links', function () {
    $version = $this->store->addVersion(($this->document)(), ($this->pdf)(), $this->author);
    $url = $this->store->temporaryUrl($version, 5);

    $this->travel(6)->minutes();
    $this->actingAs($this->author)->get($url)->assertForbidden();
});

it('denies other agencies', function () {
    $version = $this->store->addVersion(($this->document)(), ($this->pdf)(), $this->author);

    $this->actingAs(User::factory()->forAgency(Agency::factory()->create())->create())
        ->get($this->store->temporaryUrl($version))->assertForbidden();
    expect(AuditLog::where('event', 'document.download_denied')->exists())->toBeTrue();
});

it('limits confidential documents to the author and auditors', function () {
    $version = $this->store->addVersion(($this->document)(DocumentClassification::Confidential), ($this->pdf)(), $this->author);
    $auditor = User::factory()->forAgency($this->agency)->create();
    grant($auditor, 'auditor');

    $this->actingAs(User::factory()->forAgency($this->agency)->create())->get($this->store->temporaryUrl($version))->assertForbidden();
    $this->actingAs($auditor)->get($this->store->temporaryUrl($version))->assertOk();
});

it('keeps sealed bid documents unreadable until opening, even for their author', function () {
    $doc = ($this->document)(DocumentClassification::SealedBid);
    $version = $this->store->addVersion($doc, ($this->pdf)(), $this->author);

    $this->actingAs($this->author)->get($this->store->temporaryUrl($version))->assertForbidden();

    $doc->update(['unsealed_at' => now()]);
    $this->actingAs($this->author)->get($this->store->temporaryUrl($version))->assertOk();
});

it('refuses to serve content whose hash no longer matches', function () {
    $version = $this->store->addVersion(($this->document)(), ($this->pdf)(), $this->author);
    Storage::disk('local')->put($version->storage_key, 'altered');

    $this->actingAs($this->author)->get($this->store->temporaryUrl($version))->assertStatus(409);
});

it('sends guests to the login page', function () {
    $version = $this->store->addVersion(($this->document)(), ($this->pdf)(), $this->author);

    $this->get($this->store->temporaryUrl($version))->assertRedirect('/admin/login');
});
