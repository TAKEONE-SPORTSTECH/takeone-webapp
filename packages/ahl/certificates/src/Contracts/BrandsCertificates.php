<?php

namespace Ahl\Certificates\Contracts;

use Ahl\Certificates\Models\Certificate;
use Ahl\Certificates\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Model;

/**
 * Optional, for a Host that dresses its certificates: the accent colour the built-in
 * backgrounds are drawn in, the logo a watermark uses, and sample details for a preview.
 */
interface BrandsCertificates
{
    /**
     * @return array{accent?:string|null, watermark?:string|null, logos?:list<string>, seal?:string|null}
     *   accent: '#rrggbb'; watermark: raw image bytes; logos: up to two data: URIs printed at the top
     *   (leading, trailing); seal: a short word printed in the seal (null draws no seal)
     */
    public function brand(?Certificate $certificate, CertificateTemplate $template, ?Model $context, string $locale): array;

    /** `details` for the sample certificate the template editor previews. */
    public function previewDetails(string $type, ?Model $context): array;
}
