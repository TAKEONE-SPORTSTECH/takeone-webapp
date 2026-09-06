<?php

namespace App\Models;

use App\Media\Contracts\VaultDriver;
use App\Media\Drivers\MountDriver;
use App\Media\Drivers\SmbDriver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One attached place to keep media. There may be several; there may be none.
 *
 * None is the default and the ordinary case — see the migration. A vault is
 * something an operator ATTACHES when they have storage to give the platform,
 * and detaching it is a supported act rather than a disaster.
 *
 * The password is encrypted at rest and $hidden, so it cannot be serialised into
 * a JSON response by accident. It is write-only from the browser's side.
 */
class MediaVault extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'driver', 'host', 'port', 'share', 'username', 'password', 'domain',
        'mount_path', 'root_path', 'priority', 'enabled', 'read_only',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'enabled' => 'boolean',
        'read_only' => 'boolean',
        'priority' => 'integer',
        'port' => 'integer',
        'free_bytes' => 'integer',
        'total_bytes' => 'integer',
        'last_checked_at' => 'datetime',
    ];

    protected $hidden = ['password'];

    /** A mounted share, or SMB spoken by hand. See the driver classes. */
    public const DRIVER_MOUNT = 'mount';

    public const DRIVER_SMB = 'smb';

    public const DRIVERS = [self::DRIVER_MOUNT, self::DRIVER_SMB];

    /** uuid is the public handle; the numeric id never appears in a URL. */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'vault_id');
    }

    /** Eligible to receive NEW media. Reads never consult this. */
    public function scopeWritable($query)
    {
        return $query->where('enabled', true)->where('read_only', false);
    }

    public function scopeAttached($query)
    {
        return $query->where('enabled', true);
    }

    /** The driver that can actually move bytes for this vault. */
    public function driver(): VaultDriver
    {
        return match ($this->driver) {
            self::DRIVER_MOUNT => new MountDriver((string) $this->mount_path, $this->root_path),
            self::DRIVER_SMB => new SmbDriver(
                host: (string) $this->host,
                share: (string) $this->share,
                username: $this->username,
                password: $this->password,
                domain: $this->domain,
                rootPath: $this->root_path,
                port: $this->port ?: 445,
            ),
            default => throw new \RuntimeException("Unknown media vault driver [{$this->driver}]."),
        };
    }

    /** Whether video on this vault streams without landing on our disk. */
    public function servesInPlace(): bool
    {
        return $this->driver === self::DRIVER_MOUNT;
    }

    /** Where it points, said the way an operator would say it. */
    public function getLocationAttribute(): string
    {
        return $this->driver === self::DRIVER_MOUNT
            ? (string) $this->mount_path.($this->root_path ? '/'.trim($this->root_path, '/') : '')
            : '\\\\'.$this->host.'\\'.$this->share.($this->root_path ? '\\'.trim($this->root_path, '/') : '');
    }
}
