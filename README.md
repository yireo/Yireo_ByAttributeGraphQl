# Yireo ByAttribute GraphQL for Magento 2
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
