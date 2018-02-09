# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
- Support of cart rule 
### Changed
- Child products are now exported with type configurable

## 2.9.7
### Changed
- Changed the GitHub composer naming so that it does not clash with Marketplace repo

## [2.9.6]
### Fixed
- Exceptions during item export
### Changed
- Composer file details

## 2.9.5
### Fixed
- Incorrect permission reference in acl.xml
- An issue with our logger printing an Array into the returned check_cart JSON

## 2.9.4
### Added
- Review export via XML
### Fixed
- Export of shipping methods for Magento 2 Enterprise Edition

## 2.9.3
### Added
- Implemented check_stock call
- Added shipping methods to check_cart export
### Fixed
- Coupons will return gross/net price based on magento settings

## 2.9.0
### Added
- get_categories call
- get_items call
- get_customer call
- check_cart call

[Unreleased]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.7...HEAD
[2.9.6]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.6...2.9.7
