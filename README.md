# Statamic Structured Data

This Statamic addon provides a powerful and flexible way to add structured data (JSON-LD) to your Statamic website. It allows you to define structured data templates and automatically inject them into your pages, improving your site's SEO and making your content more understandable for search engines.

## Features

- 🔄 Dynamic JSON-LD generation based on entry data
- 📝 Template-based structured data configuration
- 🔌 Automatic injection of structured data into your pages
- 🎯 Support for multiple schemas per page
- 🛠 Antlers template parsing support
- 💪 Flexible and extensible architecture

## Requirements

- PHP ^8.2 or ^8.3
- Laravel ^11.0
- Statamic ^5.0

## Installation

You can install this addon via Composer:

```bash
composer require justbetter/statamic-structured-data
```

After installation, you need to publish the configuration file:

```bash
php artisan vendor:publish --tag=justbetter-structured-data
```

You should configure the collections that should have structured data templates in the `config/justbetter/structured-data.php` file.

## Usage

### 1. Creating Structured Data Templates

Create templates in your Statamic control panel that define your structured data schemas. Each template can contain multiple schema definitions with:

- Special properties (@context, @type, @id)
- Custom fields with various data types (strings, arrays, objects)
- Dynamic values using Antlers templating syntax

### 2. Assigning Templates to Entries

In your entry's content, you can assign one or more structured data templates using the `structured_data_templates` field. The addon will automatically process these templates and generate the appropriate JSON-LD scripts.

### 3. Automatic Injection

The addon automatically injects the generated JSON-LD scripts into your pages before the closing `</body>` tag. No additional configuration is required.

## Example Schema

Here's an example of how you might structure a basic Organization schema:

```json
{
  "specialProps": {
    "context": "https://schema.org",
    "type": "Organization",
    "id": "https://example.com"
  },
  "fields": [
    {
      "key": "name",
      "type": "string",
      "value": "{{ company_name }}"
    },
    {
      "key": "url",
      "type": "string",
      "value": "{{ config:app:url }}"
    }
  ]
}
```

## Configuration

The addon works out of the box with sensible defaults. Configuration can be customized through your entry blueprints and templates.
