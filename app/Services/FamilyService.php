<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRelationship;
use App\Models\Invoice;
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\Mail;

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
        // Normalize gender value to match database enum (m/f)
        $gender = $data['gender'];
        if ($gender === 'male') {
            $gender = 'm';
        } elseif ($gender === 'female') {
            $gender = 'f';
        }

        // Create the dependent user
        $dependent = User::create([
            'name' => $data['full_name'], // Required by database
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'gender' => $gender,
            'birthdate' => $data['birthdate'],
            'blood_type' => $data['blood_type'] ?? 'Unknown',
            'nationality' => $data['nationality'],
            'addresses' => $data['addresses'] ?? [],
            'social_links' => $data['social_links'] ?? [],
            'media_gallery' => $data['media_gallery'] ?? [],
        ]);

        // Create the relationship between guardian and dependent
        $relationship = UserRelationship::create([
            'guardian_user_id' => $guardian->id,
            'dependent_user_id' => $dependent->id,
            'relationship_type' => $data['relationship_type'],
            'is_billing_contact' => $data['is_billing_contact'] ?? false,
        ]);

        // Send welcome email if dependent has an email address
        if (!empty($dependent->email)) {
            Mail::to($dependent->email)->send(new WelcomeEmail($dependent, $guardian, $relationship));
        }

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
