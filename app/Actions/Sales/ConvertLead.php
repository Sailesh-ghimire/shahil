<?php

namespace App\Actions\Sales;

use App\Enums\ActivityType;
use App\Enums\ContactMethodType;
use App\Enums\LeadStatus;
use App\Enums\RecordSource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Turns a lead into a contact (matched by email/phone, or new), a company (matched by name, or new)
 * and a deal in the first stage of the chosen pipeline.
 */
class ConvertLead
{
    /**
     * @param  array{deal_title?: ?string, pipeline_id?: ?int, amount?: ?int, create_company?: bool}  $options
     * @return array{contact: Contact, company: ?Company, deal: Deal}
     */
    public function handle(Lead $lead, array $options = []): array
    {
        if ($lead->isConverted()) {
            throw new InvalidArgumentException('This lead was already converted.');
        }

        $pipeline = isset($options['pipeline_id'])
            ? Pipeline::query()->findOrFail($options['pipeline_id'])
            : Pipeline::defaultForCurrentWorkspace();

        $stage = $pipeline?->firstOpenStage();

        if ($stage === null) {
            throw new InvalidArgumentException('Set up a pipeline with at least one open stage first.');
        }

        return DB::transaction(function () use ($lead, $options, $pipeline, $stage): array {
            $contact = $this->resolveContact($lead);
            $company = ($options['create_company'] ?? true) ? $this->resolveCompany($lead, $contact) : null;

            $deal = Deal::query()->create([
                'team_id' => $lead->team_id,
                'title' => $options['deal_title'] ?? ($lead->company_name ?: $lead->name),
                'company_id' => $company?->getKey(),
                'contact_id' => $contact->getKey(),
                'pipeline_id' => $pipeline->getKey(),
                'stage_id' => $stage->getKey(),
                'amount' => $options['amount'] ?? $lead->estimated_value,
                'owner_id' => $lead->owner_id ?? auth()->id(),
                'lead_id' => $lead->getKey(),
            ]);

            $lead->forceFill([
                'status' => LeadStatus::Converted,
                'converted_contact_id' => $contact->getKey(),
                'converted_company_id' => $company?->getKey(),
                'converted_deal_id' => $deal->getKey(),
                'converted_at' => now(),
            ])->save();

            $lead->logActivity(ActivityType::StageChange, title: 'Converted to deal: '.$deal->title);
            $deal->logActivity(ActivityType::Note, title: 'Created from lead', body: $lead->notes);

            return ['contact' => $contact, 'company' => $company, 'deal' => $deal];
        });
    }

    protected function resolveContact(Lead $lead): Contact
    {
        $existing = $lead->contact;

        if ($existing === null && filled($lead->email)) {
            $existing = Contact::query()->withMethod($lead->email, ContactMethodType::Email)->first();
        }

        if ($existing === null && filled($lead->phone)) {
            $existing = Contact::query()->withMethod($lead->phone, ContactMethodType::Phone)->first()
                ?? Contact::query()->withMethod($lead->phone, ContactMethodType::WhatsApp)->first();
        }

        if ($existing !== null) {
            return $existing;
        }

        [$first, $last] = array_pad(explode(' ', trim($lead->name), 2), 2, null);

        $contact = Contact::query()->create([
            'team_id' => $lead->team_id,
            'first_name' => $first,
            'last_name' => $last,
            'owner_id' => $lead->owner_id ?? auth()->id(),
            'source' => $lead->source,
            'notes' => $lead->notes,
        ]);

        $phoneType = $lead->source === RecordSource::WhatsApp ? ContactMethodType::WhatsApp : ContactMethodType::Phone;
        $handleType = $lead->source === RecordSource::Facebook ? ContactMethodType::Facebook : ContactMethodType::Instagram;

        foreach ([[ContactMethodType::Email, $lead->email], [$phoneType, $lead->phone], [$handleType, $lead->handle]] as [$type, $value]) {
            if (filled($value)) {
                $contact->methods()->create(['team_id' => $lead->team_id, 'type' => $type, 'value' => $value, 'is_primary' => true]);
            }
        }

        return $contact;
    }

    protected function resolveCompany(Lead $lead, Contact $contact): ?Company
    {
        if (blank($lead->company_name)) {
            return $contact->companies()->first();
        }

        $company = Company::query()->firstOrCreate(
            ['team_id' => $lead->team_id, 'name' => trim($lead->company_name)],
            ['owner_id' => $lead->owner_id ?? auth()->id()],
        );

        $contact->companies()->syncWithoutDetaching([$company->getKey() => ['team_id' => $lead->team_id]]);

        return $company;
    }
}
