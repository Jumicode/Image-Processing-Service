# Image Transformation API

This project is a backend API built with Laravel that allows users to upload, transform, and manage images using Cloudflare R2 as the storage solution. The API supports several image transformations such as resize, crop, rotate, format conversion, applying filters (grayscale and sepia), compressing, and adding a watermark. Each transformation is applied in a chain according to the request payload.

## Features

- **User Authentication:** Only authenticated users can upload and manage their images.
- **Image Upload:** Users can upload images to Cloudflare R2.
- **Transformations:** The API supports:
  - **Resize:** Change the image dimensions.
  - **Crop:** Crop a specific portion of the image.
  - **Rotate:** Rotate the image by a given angle.
  - **Format Conversion:** Change the image format (jpeg, png, gif).
  - **Filters:** Apply grayscale or sepia effects.
  - **Compression:** Adjust the image quality to reduce file size.
  - **Watermark:** Overlay a watermark image on the transformed image.
- **Image Detail:** Retrieve the details and URL of an individual image.
- **Pagination:** List images with pagination support.

## Requirements

- PHP 7.4+ or higher
- Composer
- Laravel Framework
- Cloudflare R2 account (configured as an S3 driver)
- PHP GD extension enabled

## Installation

1. **Clone the repository:**

   ```bash
   git clone https://github.com/yourusername/your-repo.git
   cd your-repo
   ```

2. **Install dependencies:**

   ```bash
   composer install
   ```

3. **Configure your environment:**

   Copy the `.env.example` file to `.env` and update the necessary variables including your Cloudflare R2 credentials:

   ```dotenv
   R2_KEY=your-access-key
   R2_SECRET=your-secret-key
   R2_BUCKET=your-bucket-name
   R2_ENDPOINT=https://your-account-id.r2.cloudflarestorage.com
   R2_REGION=auto
   ```

4. **Run migrations:**

   ```bash
   php artisan migrate
   ```

5. **Start the development server:**

   ```bash
   php artisan serve
   ```

## API Endpoints

### Image Upload

- **Endpoint:** `POST /upload`
- **Description:** Upload an image to Cloudflare R2.
- **Payload Example:**

  ```json
  {
    "image": (file)
  }
  ```

### Image Transformation

- **Endpoint:** `POST /images/:id/transform`
- **Description:** Apply a series of transformations to the image.
- **Payload Example:**

  ```json
  {
    "transformations": {
      "resize": {
        "width": 800,
        "height": 600
      },
      "crop": {
        "width": 400,
        "height": 300,
        "x": 50,
        "y": 50
      },
      "rotate": 90,
      "format": "png",
      "filters": {
        "grayscale": false,
        "sepia": true
      },
      "compress": 80,
      "watermark": {
        "image": "https://example.com/path/to/watermark.png",
        "x": 10,
        "y": 10,
        "opacity": 50
      }
    }
  }
  ```

### Get Image Details

- **Endpoint:** `GET /images/:id`
- **Description:** Retrieve details and the public URL of an image.

### List Images (Paginated)

- **Endpoint:** `GET /images?page=1&limit=10`
- **Description:** Retrieve a paginated list of the user's images.
- **Query Parameters:**
  - `page`: Page number.
  - `limit`: Number of items per page.

## Authentication

All endpoints (except for public ones) are protected by authentication middleware. Use Laravel’s built-in authentication or a package such as Laravel Breeze or Laravel Fortify to manage user authentication.

## Contributing

Feel free to fork this repository and submit pull requests. For major changes, please open an issue first to discuss what you would like to change.

## License

This project is licensed under the MIT License.

---

If you need any further assistance or have any questions regarding the implementation or usage of the API, please feel free to reach out.

Happy coding!
