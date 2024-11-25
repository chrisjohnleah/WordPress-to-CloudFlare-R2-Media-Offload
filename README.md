# R2 Media Offload

R2 Media Offload is a WordPress plugin that offloads your media library to [R2-compatible object storage](https://www.cloudflare.com/products/r2/) using an S3-compatible API. This reduces server load and leverages robust, cost-effective object storage for serving your media files.

## Features

- **Automatic Uploads**: Automatically upload new media files and their thumbnails to R2-compatible storage upon upload.
- **Existing Media Migration**: Migrate your existing WordPress media library to R2-compatible storage with a simple click.
- **Delete Local Media**: Option to delete local copies of media files after uploading to storage to save server disk space.
- **Customizable Settings**: Configure access keys, bucket name, endpoint, and public bucket URL through the plugin settings page.
- **Public Bucket URL**: Specify a custom public URL for your bucket, useful when using a custom domain or CDN.
- **Flexible Control**: Choose whether to keep local media files on your server or rely solely on R2-compatible storage.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- AWS SDK for PHP (included in the plugin)

## Installation

1. **Clone or Download the Plugin**
   - Clone the repository:
     ```bash
     git clone https://github.com/andrejsrna/WordPress-R2-Media-Offload.git
     ```
   - Or [download the ZIP file](https://github.com/andrejsrna/WordPress-R2-Media-Offload/archive/refs/heads/main.zip) and extract it to your WordPress plugins directory (`wp-content/plugins/`).

2. **Activate the Plugin**
   - Go to the WordPress admin dashboard.
   - Navigate to **Plugins** and activate "R2 Media Offload."

3. **Configure Settings**
   - Navigate to **Settings > R2 Media Offload**.
   - Enter your storage credentials, bucket name, and endpoint.
   - Save the settings and start offloading media!

## Usage

1. Upload new media to your WordPress site, and it will automatically be uploaded to R2-compatible storage.
2. Use the "Migrate Media" feature in the plugin settings to offload your existing media library.
3. Optionally enable the "Delete Local Media" setting to save server storage.

## Contributing

Contributions are welcome! Please fork the repository and submit a pull request with your changes. Ensure your code adheres to WordPress coding standards.

## License

This project is licensed under the GPLv2 (or later) license. See the [LICENSE](LICENSE) file for details.

---

For questions, feedback, or support, visit [andrejsrna.sk](https://andrejsrna.sk).
