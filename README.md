# Yireo ByAttribute GraphQL for Magento 2

<!-- badges.specs.start -->
![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/yireo/magento2-byattribute-graph-ql)
<!-- badges.specs.end -->

This Magento 2 extension adds a GraphQL endpoint for accessing product attributes via GraphQL. This requires at least Magento 2.3 or higher.

To install this module, run:

    composer require yireo/magento2-byattribute-graph-ql
    ./bin/magento module:enable Yireo_ByAttributeGraphQl

### Sample GraphQL queries
Here are some sample GraphQL queries to show the usage of this extension:

```graphql
{
  productAttribute(code:"material") {
    id
    code
    label
    default_value
    options {
      value
      label
      product_count
    }
  }
}
```

Or if you want to return less information (and include a category filter):

```graphql
{
  productAttribute(code:"color", category_id: 42) {
    id
    options {
      value
    }
  }
}
```

# TODO
- Cache vital parts of product count
- Refactor `ProductCounter` and split it up in smaller classes

## Current status

<!-- badges.test.start -->
![Static Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ByAttributeGraphQl/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ByAttributeGraphQl/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ByAttributeGraphQl/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ByAttributeGraphQl/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ByAttributeGraphQl/compile.yml?label=compile)
<!-- badges.test.end -->
