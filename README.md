# Statamic Structured Data

This Statamic addon provides a powerful and flexible way to add structured data (JSON-LD) to your Statamic website. It allows you to define structured data templates and automatically inject them into your pages, improving your site's SEO and making your content more understandable for search engines.

## Features

- 🔄 Dynamic JSON-LD generation based on entry and term data
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

After installing make sure to load the Structured Data tag in your head.

``` blade
{!! Statamic::tag('structured-data:head')->fetch() !!}
```

## Configuration

Make sure to publish the config by running:

```bash
php artisan vendor:publish --tag=justbetter-structured-data
```

You can now find the config file at `config/justbetter/structured-data.php`.
After publishing the config, you can set the collections and taxonomies that should have structured data templates.

## Usage

### 1. Creating Structured Data Templates

Create templates in your Statamic control panel that define your structured data schemas. Each template can contain multiple schema definitions with:

- Special properties (@context, @type, @id)
- Custom fields with various data types (strings, numeric, arrays, objects)
- Dynamic values using Antlers templating syntax

### 2. Assigning Templates to Entries

In your entry or term's content, you can assign one or more structured data templates using the `structured_data_templates` field. The addon will automatically process these templates and generate the appropriate JSON-LD scripts.

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

The addon works out of the box with sensible defaults. Configuration can be customized through your entry and term blueprints and templates.
