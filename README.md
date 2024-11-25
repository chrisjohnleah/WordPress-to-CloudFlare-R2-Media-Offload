# Cloudflare R2 Media Offload

Cloudflare R2 Media Offload is a WordPress plugin that offloads your media library to [Cloudflare R2](https://www.cloudflare.com/products/r2/), reducing server load and leveraging Cloudflare's robust and cost-effective object storage for serving your media files.

## Features

- **Automatic Uploads**: Automatically upload new media files and their thumbnails to Cloudflare R2 upon upload.
- **Existing Media Migration**: Migrate your existing WordPress media library to Cloudflare R2 with a simple click.
- **Delete Local Media**: Option to delete local copies of media files after uploading to R2 to save server disk space.
- **Customizable Settings**: Configure access keys, bucket name, endpoint, and public bucket URL through the plugin settings page.
- **Public Bucket URL**: Specify a custom public URL for your R2 bucket, useful when using a custom domain or CDN.
- **Flexible Control**: Choose whether to keep local media files on your server or rely solely on Cloudflare R2.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- AWS SDK for PHP (included in the plugin)

## Installation

1. **Clone or Download the Plugin**

   - Clone the repository:
     ```bash
     git clone https://github.com/yourusername/cloudflare-r2-media-offload.git
     ```
   - Or [download the ZIP file](https://github.com/yourusername/cloudflare-r2-media-offload/archive/refs/heads/main.zip) and extract it to your WordPress plugins directory (`wp-content/plugins/`).
