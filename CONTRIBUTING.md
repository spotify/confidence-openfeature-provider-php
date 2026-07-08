# Contributing

We welcome contributions and are happy to discuss ideas, answer questions, and help you get started.
**Before opening a pull request**, please open an issue or start a discussion with the maintainers.

## Development

### Prerequisites

- PHP >= 8.2
- [Composer](https://getcomposer.org/)

### Setup

```sh
make install
```

### Testing

Run unit tests:

```sh
make test
```

Run end-to-end tests (requires a `CONFIDENCE_CLIENT_SECRET` environment variable):

```sh
make test-e2e
```

### Linting

```sh
make lint
```

## License

Contributions are licensed under the [Apache 2.0 License](LICENSE).
