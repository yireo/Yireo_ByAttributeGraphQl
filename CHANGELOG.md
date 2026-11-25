# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 25 November 2026
### Added
- The `productAttribute` query is now cacheable in the GraphQL full page cache, and product counts are cached per attribute, store and category

### Changed
- BREAKING: `product_count` is now returned as an `Int` instead of a `String`
- Product counts now only include enabled, visible products that are in stock and assigned to the current store and website, instead of every visible product of a hard-coded attribute
- Requires Magento 2.4.6 or newer and PHP 8.2 up to 8.5; PHP 8.1 is no longer supported

### Fixed
- Security: the `code` argument only accepts valid codes of filterable select and multiselect attributes, so options of other attributes are no longer exposed
- Security: the `category_id` argument only accepts active categories within the current store
- Deprecation warnings on PHP 8.4 about implicitly nullable resolver parameters
- The dependency on `laminas/laminas-db` is gone, as the Magento DB select is used instead

## [0.1.1] - 29 July 2020
### Added
- Magento 2.4 compatibility

## [0.1.0] - Undocumented
