<?php

namespace App\Certificates\Support;

use Ahl\Certificates\Models\Certificate;
use Ahl\Certificates\Policies\CertificatePolicy as EnginePolicy;
use App\Members\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may do what with a certificate. Deny by default; every rule is decided
 * against the certificate's CONTEXT (an event's managers run its certificates)
 * or its RECIPIENT (a person sees their own, and only once it is approved).
 *
 * The package's `certificates.*` abilities are not used: TakeOne has no such
 * permissions, and a role check here would be a second, drifting copy of
 * EventAccess.
 */
class CertificatePolicy extends EnginePolicy
{
    public function __construct(private Contexts $contexts) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $user instanceof User;
    }

    /** The recipient, once approved or revoked (revoked says so); the context's managers always. */
    public function view(Authenticatable $user, Certificate $certificate): bool
    {
        if (! $user instanceof User) {
            return false;
        }
        if ($this->manages($user, $certificate)) {
            return true;
        }

        return ! $certificate->isDraft() && $this->isRecipient($user, $certificate->recipient);
    }

    public function issueFor(Authenticatable $user, Model $recipient): bool
    {
        return false;   // drafts are made by a context's generate(), which authorises the context
    }

    public function update(Authenticatable $user, Certificate $certificate): bool
    {
        return $certificate->isDraft() && $user instanceof User && $this->manages($user, $certificate);
    }

    public function delete(Authenticatable $user, Certificate $certificate): bool
    {
        return $this->update($user, $certificate);
    }

    public function approve(Authenticatable $user, Certificate $certificate): bool
    {
        return $certificate->isDraft() && $user instanceof User && $this->manages($user, $certificate);
    }

    public function revoke(Authenticatable $user, Certificate $certificate): bool
    {
        return $certificate->isApproved() && $user instanceof User && $this->manages($user, $certificate);
    }

    public function send(Authenticatable $user, Certificate $certificate): bool
    {
        return $certificate->isApproved() && $user instanceof User && $this->manages($user, $certificate);
    }

    /** A print copy (for pre-printed paper) is the organiser's, whatever the status. */
    public function print(Authenticatable $user, Certificate $certificate): bool
    {
        return $user instanceof User && $this->manages($user, $certificate);
    }

    public function manageTemplates(Authenticatable $user): bool
    {
        return false;   // decided per context by DesignController, never globally
    }

    public function viewRecipient(Authenticatable $user, Model $recipient): bool
    {
        return $this->isRecipient($user, $recipient);
    }

    public function manageRecipient(Authenticatable $user, Model $recipient): bool
    {
        return false;
    }

    private function manages(User $user, Certificate $certificate): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        $context = $certificate->context_id ? $certificate->context : null;
        $provider = $this->contexts->forModel($context);

        return $provider && $context && $provider->canManage($context, $user);
    }
}
