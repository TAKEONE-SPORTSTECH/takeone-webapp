<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRelationship;
use App\Models\Invoice;

class FamilyService
{
    /**
     * Create a new dependent user and link it to the guardian.
     *
     * @param User $guardian The guardian user
     * @param array $data The dependent user data
     * @return User The newly created dependent user
     */
    public function createDependent(User $guardian, array $data): User
    {
        // Create the dependent user
        $dependent = User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'gender' => $data['gender'],
            'birthdate' => $data['birthdate'],
            'blood_type' => $data['blood_type'] ?? null,
            'nationality' => $data['nationality'],
            'addresses' => $data['addresses'] ?? [],
            'social_links' => $data['social_links'] ?? [],
            'media_gallery' => $data['media_gallery'] ?? [],
        ]);

        // Create the relationship between guardian and dependent
        UserRelationship::create([
            'guardian_user_id' => $guardian->id,
            'dependent_user_id' => $dependent->id,
            'relationship_type' => $data['relationship_type'],
            'is_billing_contact' => $data['is_billing_contact'] ?? false,
        ]);

        return $dependent;
    }

    /**
     * Get all invoices where the guardian is the payer.
     *
     * @param int $guardianId The guardian user ID
     * @return \Illuminate\Database\Eloquent\Collection The invoices
     */
    public function getFamilyInvoices(int $guardianId)
    {
        return Invoice::where('payer_user_id', $guardianId)
            ->with('student')
            ->get();
    }
}
