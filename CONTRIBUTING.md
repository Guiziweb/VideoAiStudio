# Contributing to VideoAI Studio

Thank you for your interest in contributing to VideoAI Studio! This document provides guidelines and instructions for contributing to the project.

## 🚀 Getting Started

### Prerequisites
- PHP 8.3+
- Composer 2.x
- Node.js 18+ and Yarn
- Docker for local development
- Git

### Development Setup
1. Fork and clone the repository
2. Copy `.env.example` to `.env.local` and configure your settings
3. Run `make init` to set up the complete development environment
4. Run `make serve` to start the development server

## 📝 Development Workflow

### Branch Strategy
- `main` - Production-ready code
- `develop` - Integration branch for features
- `feature/*` - New features
- `fix/*` - Bug fixes
- `docs/*` - Documentation updates

### Commit Messages
Follow conventional commits format:
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation changes
- `style:` Code style changes
- `refactor:` Code refactoring
- `test:` Test additions/modifications
- `chore:` Maintenance tasks

Example: `feat(wallet): add transaction history chart`

## 🏗️ Architecture Guidelines

### Domain Structure
Follow the established domain-driven design:
```
src/
├── Video/          # Video generation domain
│   ├── Entity/
│   ├── Service/
│   ├── Controller/
│   └── Fixture/
├── Wallet/         # Wallet & tokens domain
│   ├── Entity/
│   ├── Service/
│   ├── Controller/
│   └── Component/
└── Shared/         # Shared utilities
```

### Code Standards

#### PHP Standards
- Follow PSR-12 coding standard
- Use PHP 8+ features (attributes, enums, readonly properties)
- Type declarations for all parameters and return types
- Use dependency injection via constructor

#### Sylius Patterns
- Use Sylius Resources for entities
- Follow Sylius hooks system for templates
- Extend Sylius services properly
- Use Sylius grids for admin lists

## ✅ Testing

### Running Tests
```bash
# PHPUnit tests
make phpunit

# Code quality checks
make static

# PHPStan analysis
vendor/bin/phpstan analyse

# ECS code style
vendor/bin/ecs check
```

### Test Requirements
- Unit tests for services and utilities
- Integration tests for critical workflows
- Minimum 80% code coverage for business logic
- All tests must pass before PR merge

## 📦 Pull Request Process

### Before Submitting
1. Ensure all tests pass: `make phpunit`
2. Run quality checks: `make static`
3. Update documentation if needed
4. Add/update tests for new functionality
5. Follow the existing code style

### PR Guidelines
1. Create PR against `develop` branch
2. Use descriptive PR title following commit convention
3. Fill out the PR template completely
4. Link related issues using keywords (Fixes #123)
5. Request review from maintainers

### PR Template
```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tests pass locally
- [ ] Added new tests
- [ ] Updated existing tests

## Checklist
- [ ] Code follows project standards
- [ ] Self-reviewed code
- [ ] Updated documentation
- [ ] No security vulnerabilities
```

## 🛡️ Security

### Security Guidelines
- Never commit sensitive data (passwords, API keys)
- Use environment variables for configuration
- Follow OWASP security best practices
- Report security issues privately to maintainers

### Reporting Security Issues
Please email security issues to [security@example.com] instead of using public issue tracker.

## 📚 Documentation

### Documentation Requirements
- Update README.md for user-facing changes
- Update CLAUDE.md for architectural changes
- Add inline documentation for complex logic
- Document new configuration options

### Code Documentation
```php
/**
 * Calculate the token cost for video generation
 * 
 * @param VideoGeneration $generation The video generation entity
 * @return int The calculated token cost
 * @throws InsufficientTokensException When user lacks tokens
 */
public function calculateCost(VideoGeneration $generation): int
{
    // Implementation
}
```

## 🎯 Coding Best Practices

### DRY (Don't Repeat Yourself)
- Extract common functionality to services
- Use traits for shared entity behavior
- Create reusable components

### SOLID Principles
- Single Responsibility Principle
- Open/Closed Principle
- Liskov Substitution Principle
- Interface Segregation Principle
- Dependency Inversion Principle

### Performance
- Use database indexes appropriately
- Implement caching where beneficial
- Optimize queries (avoid N+1 problems)
- Profile code for bottlenecks

## 🤝 Community

### Code of Conduct
- Be respectful and inclusive
- Welcome newcomers and help them get started
- Focus on constructive criticism
- Respect differing opinions

### Getting Help
- Check existing issues and documentation
- Ask questions in discussions
- Join our community chat
- Attend community meetings

## 📄 License

By contributing, you agree that your contributions will be licensed under the same license as the project (see LICENSE file).

## 🙏 Recognition

Contributors will be recognized in:
- Contributors list in README
- Release notes
- Project website

Thank you for contributing to make this project better! 🎉