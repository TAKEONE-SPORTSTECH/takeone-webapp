# Uploads

How an uploaded file reaches disk, who may write it, and who may read it back.
The STRICT rules behind this live in `CLAUDE.md` (Part 8: *Upload Storage
Structure*, *Image Uploads Must Validate Real Bytes*, *A Person's Photo Has One
Home*). This page is the map.

## The path of an image upload

1. **A screen posts a base64 data-URI** to the endpoint it was given through its
   `uploadUrl` (`<x-takeone-cropper>`, `<x-image-upload>`, `<x-profile-modal>`, …).
   Every product surface has its own endpoint, which authorises the action
   (e.g. `member.upload-picture`, `me.events.cover.store`).
2. **The endpoint stores it through `App\Traits\StoresBase64Images`.** That trait
   reads the real MIME type from the bytes, refuses anything that is not
   jpg/png/gif/webp (SVG is refused because it can carry script), and assigns the
   extension itself.
3. **The folder comes from `App\Support\StoragePath`**, built from the owner's
   public id: `members/{uuid}/…`, `clubs/{slug}/…`, `events/{uuid}/…`. The
   filename is generated on the server. Neither ever comes from the request.
4. **Reading it back goes through `/file/{path}`** (`file.show`), where
   `App\Support\FileAccess` decides per path who may see it. There is no public
   storage symlink.

## `POST /image-upload` (route `image.upload`)

The `takeone/cropper` package registers this route itself, with only the `web`
middleware, and its handler wrote to whatever folder, filename and extension the
client named. That let anybody, signed in or not, plant or overwrite files in the
uploads tree.

Since 2026-10-31 the same URL and route name are answered by
`App\Members\Controllers\CropperUploadController` (registered in
`app/Members/routes.php`). Module routes load after every package has booted, so
it replaces the package's route rather than adding a second one.

| | Before (package) | Now |
|---|---|---|
| Who may call | anyone | signed in, verified, past 2FA |
| Rate limit | none | `throttle:uploads` (20/min per user) |
| Type check | the client's data-URI header | the real bytes |
| Destination | client's `folder/filename.ext` | `members/{uploader-uuid}/uploads/{uuid}.{ext}` |
| Readable by | n/a | the uploader and those who speak for them (`FileAccess`, purpose `uploads`) |
| Response | `{success, path, url}` | same keys; `url` is now a working `/file/…` link |

`folder` and `filename` are still accepted, so an old client is not refused, but
they are ignored. No screen in the product posts here; the route stays only so
an old client gets a safe answer. Tests: `tests/Feature/Members/CropperUploadRouteTest.php`.

**Remaining work** (registered in `Documentation/HOUSE-CLEANING.md`): fix the
package upstream so it no longer registers the route, then retire this shadow
once `route:list` shows no `/image-upload`.

## Scalability assumptions

- **One shared uploads root.** All files go to the `public` disk, whose root is
  `UPLOADS_ROOT` (or `storage/app` when unset). A second web node must mount the
  same folder; local disk on each node would split the files.
- **Base64 in the request body.** Images are posted inline, so the size limit
  is PHP's `post_max_size`. That is fine for cropped profile-sized images; large
  media (video) takes its own path through `App\Media`, not this one.
- **Synchronous writes.** An image is decoded and written during the request.
  At the current sizes (well under 1 MB after cropping) this is cheap; anything
  that needs resizing at scale should move to a queued job.
- **Upgrade path:** if uploads move to object storage (S3-compatible), only the
  `public` disk's driver changes. `StoragePath` and `FileAccess` already work on
  logical paths, and `/file/{path}` would stream or redirect to a signed URL.
