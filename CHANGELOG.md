# [1.6.0](https://github.com/Nofraud/nofraud_connect/compare/v1.5.0...v1.6.0) (2025-01-07)


### Features

* Add authorize and capture flow ([d39e509](https://github.com/Nofraud/nofraud_connect/commit/d39e509fe2c585a2e4a0e5536f0f6eac4b766ec2))
* Add status history comment for refund and void actions. ([b2b3526](https://github.com/Nofraud/nofraud_connect/commit/b2b352698d378239c86322c8dc0829aff01f9f12))
* Add status message when order cancelled. ([cd9c90c](https://github.com/Nofraud/nofraud_connect/commit/cd9c90c8a2bc0f982aa582616e9e04190d0fd68b))

# [1.5.0](https://github.com/Nofraud/nofraud_connect/compare/v1.4.2...v1.5.0) (2024-12-02)


### Features

* Add setting to skip screening for orders based on customer group ([63f53a9](https://github.com/Nofraud/nofraud_connect/commit/63f53a976e507b19744facebed15e5b7eefab6ea))

## [1.4.2](https://github.com/Nofraud/nofraud_connect/compare/v1.4.1...v1.4.2) (2024-11-15)


### Bug Fixes

* Remove dynamic property. ([f3b7186](https://github.com/Nofraud/nofraud_connect/commit/f3b7186b6acb074ae3ec4938839b21d4730891cd))

## [1.4.1](https://github.com/Nofraud/nofraud_connect/compare/v1.4.0...v1.4.1) (2024-11-11)


### Bug Fixes

* Apply parsing to remote ip as well for safety. ([03b2e89](https://github.com/Nofraud/nofraud_connect/commit/03b2e89f7623b962bce0576bf1150af22c38e747))
* Refine IP extraction from X-Forwarded-For header ([01f04e7](https://github.com/Nofraud/nofraud_connect/commit/01f04e7425ef900da11bfefd79632743535058ef))

# [1.4.0](https://github.com/Nofraud/nofraud_connect/compare/v1.3.0...v1.4.0) (2024-10-02)


### Features

* Extract AVS, CVV and BIN from cybersource. ([c9a9eb8](https://github.com/Nofraud/nofraud_connect/commit/c9a9eb89bc63fef931f6dc22f9725ffc86b3cd74))

# [1.3.0](https://github.com/Nofraud/nofraud_connect/compare/v1.2.1...v1.3.0) (2024-09-30)


### Features

* App app and version to NFAPI call. ([70a065d](https://github.com/Nofraud/nofraud_connect/commit/70a065d32a850a9d2ccf0b1b1a5b38d359cae8e8))

## [1.2.1](https://github.com/Nofraud/nofraud_connect/compare/v1.2.0...v1.2.1) (2024-02-13)


### Bug Fixes

* Add check for order's ability to be held, implement try-catch for hold operation, and improve logging ([d76d8e1](https://github.com/Nofraud/nofraud_connect/commit/d76d8e1483bd7db89d2ef4e5d36fe2123cda512d))
* **cron:** Do not update review order if decision hasn't yet been rendered ([43487aa](https://github.com/Nofraud/nofraud_connect/commit/43487aa5c02bf7a9be7f3bb16aa86c61af130c23))

# [1.2.0](https://github.com/Nofraud/nofraud_connect/compare/v1.1.0...v1.2.0) (2023-11-02)


### Features

* Add support for currency internationalization ([#39](https://github.com/Nofraud/nofraud_connect/issues/39)) ([0186921](https://github.com/Nofraud/nofraud_connect/commit/01869215d33d9675501296d4c30cb28e3b0ae8eb))

# [1.1.0](https://github.com/Nofraud/nofraud_connect/compare/v1.0.2...v1.1.0) (2023-08-31)


### Bug Fixes

* Set fraudulent status orders to configured decision for fail ([aa92ad8](https://github.com/Nofraud/nofraud_connect/commit/aa92ad8e3068e9d9b55d3dbc3e7620bf63829558))
* Update order status for failed orders when auto-cancel is disabled ([#36](https://github.com/Nofraud/nofraud_connect/issues/36)) ([0bb3820](https://github.com/Nofraud/nofraud_connect/commit/0bb3820e3cc4442ea133406a94cdf711f6a7c550))


### Features

* Fetch BIN for ParadoxLabs Auth.NET CIM ([71d2bd7](https://github.com/Nofraud/nofraud_connect/commit/71d2bd746998de7e8ef72f2bed84c61ce8601e2d))

## [1.0.2](https://github.com/Nofraud/nofraud_connect/compare/v1.0.1...v1.0.2) (2023-08-21)


### Bug Fixes

* Make Extension Compatible With Dynamic Property Deprecation in PHP 8.2 ([#33](https://github.com/Nofraud/nofraud_connect/issues/33)) ([6968aa0](https://github.com/Nofraud/nofraud_connect/commit/6968aa004805f60989a6c6970da9e45f08b11a93))

## [1.0.1](https://github.com/Nofraud/nofraud_connect/compare/v1.0.0...v1.0.1) (2023-06-30)


### Bug Fixes

* include version in composer.json to allow for FE display ([a9013ed](https://github.com/Nofraud/nofraud_connect/commit/a9013ed23bc383e7e53871c7c7894f211da10e4b))

# 1.0.0 (2023-06-20)


### Bug Fixes

* Array logs should be printed readable ([261cdd8](https://github.com/Nofraud/nofraud_connect/commit/261cdd815a71ba0e81327945e232539899d989c4))
* auto-cancel orders in fail status appropriately ([903b763](https://github.com/Nofraud/nofraud_connect/commit/903b763d807c12fb7487fca1e4f9ae5378d71062))
* Consider refund failed if invoice cannot be refunded or voided. ([65925c7](https://github.com/Nofraud/nofraud_connect/commit/65925c7e87309e377daaa1b859ec00fb7c2cb10f))
* Do not cancel if refund fails ([92ea933](https://github.com/Nofraud/nofraud_connect/commit/92ea933d15f3ed457790fd8d7b0ae9e1d605b410))
* Don't cancel if refund fails ([00c3f36](https://github.com/Nofraud/nofraud_connect/commit/00c3f360dce31d8903f5309aabebab860f371956))
* remove fraudulent condition since it is not a valid decision ([54084c0](https://github.com/Nofraud/nofraud_connect/commit/54084c0a1bed58ab76d1c465a3f0e836c23a1cc1))
* replace uninitialized var ([658cce9](https://github.com/Nofraud/nofraud_connect/commit/658cce93d97d98c1cc50a68c437c4a1eb7d9a703))
* Save order after update ([90cbd80](https://github.com/Nofraud/nofraud_connect/commit/90cbd80db6fce1a0a500ab350f3021f63d65d5cd))
* Update nofraud status on review --> fail ([e6b2ee1](https://github.com/Nofraud/nofraud_connect/commit/e6b2ee1af3f8a4cea70ab38952a3ab88956d686e))


### Features

* worked depreciated error resolving and backend configuration changes ([462c571](https://github.com/Nofraud/nofraud_connect/commit/462c571804153a3dfe29d3d6566cc7c2846fa1e3))
