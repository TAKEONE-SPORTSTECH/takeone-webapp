<?php

namespace App\Services;

use App\Mail\WelcomeEmail;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Support\Facades\Mail;

class FamilyService
{
    /**
     * Create a new dependent user and link it to the guardian.
     *
     * @param  User  $guardian  The guardian user
     * @param  array  $data  The dependent user data
     * @return User The newly created dependent user
     */
    public function createDependent(User $guardian, array $data): User
    {
        // Normalize gender value to match database enum (m/f)
        $gender = $data['gender'];
        $genderMap = ['male' => 'Male', 'female' => 'Female'];
        $gender = $genderMap[$gender] ?? $gender;

        // Create (or restore soft-deleted) dependent user
        $emergencyContacts = collect(json_decode($data['emergency_contacts_json'] ?? '[]', true) ?? [])
            ->filter(fn ($c) => ! empty($c['name']) || ! empty($c['phone']))
            ->map(fn ($c) => [
                'name' => trim($c['name'] ?? ''),
                'relationship' => $c['relationship'] ?? '',
                'phone_code' => $c['phone_code'] ?? '',
                'phone' => trim($c['phone'] ?? ''),
            ])
            ->values()
            ->all();

        $healthConditions = collect(json_decode($data['health_conditions_json'] ?? '[]', true) ?? [])
            ->filter(fn ($c) => ! empty($c['condition']))
            ->map(fn ($c) => [
                'condition' => trim($c['condition']),
                'noted_at' => $c['noted_at'] ?? now()->format('Y-m-d'),
                'notes' => trim($c['notes'] ?? ''),
            ])
            ->values()
            ->all();

        $documents = collect(json_decode($data['documents_json'] ?? '[]', true) ?? [])
            ->filter(fn ($d) => ! empty($d['type']) || ! empty($d['number']))
            ->map(fn ($d) => [
                'type' => trim($d['type'] ?? ''),
                'number' => trim($d['number'] ?? ''),
                'file_path' => $d['file_path'] ?? null,
                'file_name' => $d['file_name'] ?? null,
                'uploaded_at' => $d['uploaded_at'] ?? now()->format('Y-m-d'),
            ])
            ->values()
            ->all();

        $dependentData = [
            'name' => $data['full_name'],
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
            'mobile' => ! empty($data['mobile'] ?? []) ? $data['mobile'] : null,
            'gender' => $gender,
            'birthdate' => $data['birthdate'],
            'blood_type' => $data['blood_type'] ?? 'Unknown',
            'nationality' => $data['nationality'],
            'addresses' => $data['addresses'] ?? [],
            'social_links' => $data['social_links'] ?? [],
            'media_gallery' => $data['media_gallery'] ?? [],
            'emergency_contacts' => $emergencyContacts,
            'health_conditions' => $healthConditions,
            'documents' => $documents,
            'email_verified_at' => null,
        ];

        $softDeleted = ! empty($data['email'])
            ? User::withTrashed()->where('email', $data['email'])->whereNotNull('deleted_at')->first()
            : null;

        if ($softDeleted) {
            $softDeleted->restore();
            $softDeleted->update($dependentData);
            $dependent = $softDeleted;
        } else {
            $dependent = User::create($dependentData);
        }

        // Create the relationship between guardian and dependent
        $relationship = UserRelationship::create([
            'guardian_user_id' => $guardian->id,
            'dependent_user_id' => $dependent->id,
            'relationship_type' => $data['relationship_type'],
            'is_billing_contact' => $data['is_billing_contact'] ?? false,
        ]);

        // Send welcome email if dependent has an email address
        if (! empty($dependent->email)) {
            Mail::to($dependent->email)->queue(new WelcomeEmail($dependent, $guardian, $relationship));
        }

        return $dependent;
    }

    /**
     * Get all invoices where the guardian is the payer.
     *
     * @param  int  $guardianId  The guardian user ID
     * @return \Illuminate\Database\Eloquent\Collection The invoices
     */
    public function getFamilyInvoices(int $guardianId)
    {
        return Invoice::where('payer_user_id', $guardianId)
            ->with('student')
            ->get();
    }
}
