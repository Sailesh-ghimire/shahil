<?php

use App\Enums\ContactMethodType;
use App\Filament\Imports\CompanyImporter;
use App\Filament\Imports\ContactImporter;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Support\Spreadsheets\XlsxToCsv;
use Filament\Actions\Imports\Models\Import;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->actingInWorkspace($this->owner, $this->team);

    $this->import = Import::query()->create([
        'file_name' => 'customers.csv',
        'file_path' => 'imports/customers.csv',
        'importer' => ContactImporter::class,
        'total_rows' => 3,
        'user_id' => $this->owner->id,
    ]);
});

it('imports a contact with email, phone, company, job title and tags', function () {
    ContactImporter::test(import: $this->import, options: ['duplicates' => 'update'])
        ->import([
            'first_name' => 'Ramesh',
            'last_name' => 'Thapa',
            'email' => 'ramesh@lumbinifoods.example',
            'phone' => '9802022222',
            'whatsapp' => '',
            'company' => 'Lumbini Foods',
            'job_title' => 'Purchase head',
            'source' => 'referral',
            'owner' => '',
            'tags' => 'VIP, Dashain',
            'notes' => '',
        ])
        ->assertImported();

    $ramesh = Contact::query()->where('first_name', 'Ramesh')->firstOrFail();

    expect($ramesh->team_id)->toBe($this->team->id)
        ->and($ramesh->owner_id)->toBe($this->owner->id)
        ->and($ramesh->primaryEmail())->toBe('ramesh@lumbinifoods.example')
        ->and($ramesh->primaryPhone())->toBe('+9779802022222')
        ->and($ramesh->companies->first()->name)->toBe('Lumbini Foods')
        ->and($ramesh->companies->first()->pivot->job_title)->toBe('Purchase head')
        ->and($ramesh->tags->pluck('name')->sort()->values()->all())->toBe(['Dashain', 'VIP'])
        ->and($this->import->fresh()->created_rows)->toBe(1);
});

it('applies the duplicate rule when the email already exists', function (string $rule, int $contacts, string $expectedName, string $counter) {
    Contact::factory()->forTeam($this->team)->withEmail('ramesh@lumbinifoods.example')->create(['first_name' => 'Ramesh']);

    ContactImporter::test(import: $this->import, options: ['duplicates' => $rule])
        ->import(['first_name' => 'Ramesh Kumar', 'last_name' => 'Thapa', 'email' => 'RAMESH@lumbinifoods.example']);

    expect(Contact::query()->count())->toBe($contacts)
        ->and(Contact::query()->orderBy('id')->first()->first_name)->toBe($expectedName)
        ->and($this->import->fresh()->{$counter})->toBe(1);
})->with([
    'update' => ['update', 1, 'Ramesh Kumar', 'updated_rows'],
    'skip' => ['skip', 1, 'Ramesh', 'skipped_rows'],
    'create' => ['create', 2, 'Ramesh', 'created_rows'],
]);

it('matches duplicates by phone when there is no email', function () {
    Contact::factory()->forTeam($this->team)->withPhone('+977 980-3033333')->create(['first_name' => 'Anjali']);

    ContactImporter::test(import: $this->import, options: ['duplicates' => 'update'])
        ->import(['first_name' => 'Anjali', 'last_name' => 'Gurung', 'phone' => '9803033333']);

    expect(Contact::query()->count())->toBe(1)
        ->and(Contact::query()->first()->last_name)->toBe('Gurung')
        ->and(Contact::query()->first()->methods()->where('type', ContactMethodType::Phone)->count())->toBe(1);
});

it('fails rows with a reason', function () {
    $result = ContactImporter::test(import: $this->import)
        ->import(['first_name' => '', 'email' => 'not-an-email']);

    expect($result->errors()->keys())->toContain('first_name', 'email')
        ->and(Contact::query()->count())->toBe(0);
});

it('splits a full name in the first-name column', function () {
    ContactImporter::test(import: $this->import)
        ->import(['first_name' => 'Hari Prasad Oli'])
        ->assertImported();

    $contact = Contact::query()->firstOrFail();

    expect($contact->first_name)->toBe('Hari')
        ->and($contact->last_name)->toBe('Prasad Oli');
});

it('imports companies and matches duplicates by name', function () {
    Company::factory()->forTeam($this->team)->create(['name' => 'Lumbini Foods', 'industry' => null]);

    CompanyImporter::test(import: $this->import, options: ['duplicates' => 'update'])
        ->import(['name' => 'Lumbini Foods', 'industry' => 'FMCG', 'size' => '51-200'])
        ->assertImported();

    expect(Company::query()->count())->toBe(1)
        ->and(Company::query()->first()->industry)->toBe('FMCG');
});

it('converts the first sheet of an xlsx file to csv', function () {
    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['First name', 'Email', 'Phone']));
    $writer->addRow(Row::fromValues(['Sita', 'sita@everest.example', 9801011111]));
    $writer->addRow(Row::fromValues(['', '', '']));
    $writer->addRow(Row::fromValues(['Priya', 'priya@nimbus.example', '+977 980-5055555']));
    $writer->close();

    $csv = stream_get_contents(XlsxToCsv::convert($path));

    expect(trim($csv))->toBe("\"First name\",Email,Phone\nSita,sita@everest.example,9801011111\nPriya,priya@nimbus.example,\"+977 980-5055555\"");

    unlink($path);
});

it('builds the finished notification with created, updated and failed counts', function () {
    $this->import->update(['created_rows' => 486, 'updated_rows' => 9, 'processed_rows' => 500, 'successful_rows' => 495]);

    expect(ContactImporter::getCompletedNotificationBody($this->import))
        ->toContain('486 created')
        ->toContain('9 updated');
});
