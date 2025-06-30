# Bunny

Open Telemetry Auto Instrumentation for Bunny

![Continuous Integration](https://github.com/reactphp-inspector/bunny/workflows/Continuous%20Integration/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/react-inspector/bunny/v/stable.png)](https://packagist.org/packages/react-inspector/bunny)
[![Total Downloads](https://poser.pugx.org/react-inspector/bunny/downloads.png)](https://packagist.org/packages/react-inspector/bunny/stats)
[![Type Coverage](https://shepherd.dev/github/reactphp-inspector/bunny/coverage.svg)](https://shepherd.dev/github/reactphp-inspector/bunny)
[![License](https://poser.pugx.org/react-inspector/bunny/license.png)](https://packagist.org/packages/react-inspector/bunny)

# Installation

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require react-inspector/bunny
```

# Todo

- [X] Port initial implementation over from private project
- [ ] Add Unit tests, look at https://github.com/opentelemetry-php/contrib-auto-reactphp for inspiration
- [ ] Add spans for everything beyond the current implementation that makes sense
- [ ] Add metrics
- [ ] Write or generate documentation about everything that this package collects traces and metrics on

# License

The MIT License (MIT)

Copyright (c) 2025 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
