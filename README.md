# 🌴 PHP Palm Framework

**The Modern, Modular, Developer-Friendly PHP Framework**

> Build powerful PHP applications with clean code, zero boilerplate, and a smile on your face.

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## ✨ Why PHP Palm?

**PHP Palm** combines the simplicity of a micro-framework with the power of enterprise-grade features:

- 🚀 **Generate Full Modules in Seconds** - `palm make:module Products` creates Controller, Service, Model in one command
- 🛡️ **NestJS-Style Validation** - Use PHP 8 Attributes (`#[Required]`, `#[IsEmail]`) in your Models
- 🔌 **HMVC Architecture** - Call routes like functions (`UserModule::get('/users')`) - no HTTP overhead!
- ⚡ **ActiveRecord ORM** - Fluent queries (`User::where('active', 1)->all()`)
- 🎨 **Complete Frontend System** - Views, Components, Hot Reload, PWA, SEO tools
- 🔒 **Production-Ready Security** - CSRF, XSS, CSP, Rate Limiting out of the box

---

## 🚀 Quick Start

```bash
# 1. Install
composer create-project php-palm/framework my-app
cd my-app

# 2. Configure
cp config/.env.example config/.env
# Edit config/.env with your database credentials

# 3. Start Development Server (with hot reload!)
palm serve

# 4. Create Your First Feature
palm make:module Products
```

**That's it!** Your API is live at `http://localhost:8000/products` 🎉

---

## 💡 The Palm Way

### 1. Define Your Model (with Validation!)

No separate validation files. No configuration arrays. Just clean, declarative code:

```php
class ProductModel extends BaseModel {
    protected string $table = 'products';

    #[Required]
    #[IsString #[Length(min: 3)]
    public string $name;

    #[Required]
    #[Min(0)]
    public float $price;
}
```

### 2. Use It in Your Service

Validation happens automatically. No boilerplate:

```php
public function create(array $data) {
    // ✨ Magic: validates AND hydrates
    $product = ProductModel::validate($data);
    $product->save();
    return $product;
}
```

### 3. That's It!

The Controller, Routes, and everything else was generated for you by `palm make:module`.

---

## 📚 Features

### 🛠️ Developer Experience
- **20+ CLI Commands** - Generate code, manage migrations, optimize production
- **Hot Reload** - WebSocket-based instant browser refresh
- **Auto-completion** - Full IDE support with type hints

### 🗄️ Database
- **ActiveRecord ORM** - Intuitive database operations
- **Query Builder** - Fluent interface for complex queries
- **Migrations** - Version control for your database
- **Seeders** - Populate test data easily

### 🔐 Security
- **CSRF Protection** - Auto-injected tokens
- **XSS Protection** - Safe output helpers
- **SQL Injection Protection** - Prepared statements
- **Security Headers** - CSP, X-Frame-Options, HSTS
- **Rate Limiting** - Per-route protection

### 🎨 Frontend
- **Palm Views** - PHP templating with `.palm.php` extension
- **Component System** - Reusable UI components
- **PWA Support** - Progressive Web App generation
- **SEO Tools** - Meta tags, sitemaps, Open Graph
- **Asset Optimization** - Minification, lazy loading

### ⚡ Performance
- **Route Caching** - Zero overhead in production
- **View Caching** - Pre-compiled templates
- **Gzip/Brotli** - Automatic compression
- **Progressive Loading** - Smart asset management
- **Internal Calls** - HMVC pattern (no HTTP overhead)

---

## 📖 Documentation

### **[👉 Read the Complete Documentation (DOCS.md)](DOCS.md)**

**Quick Links:**
- [CLI Commands Reference](DOCS.md#cli-commands)
- [Module System Guide](DOCS.md#module-system)
- [Validation Attributes](DOCS.md#model-validation)
- [ActiveRecord ORM](DOCS.md#activerecord-orm)
- [Routing & Internal Calls](DOCS.md#routing)
- [Frontend Features](DOCS.md#frontend-features)

**Additional Guides:**
- [ActiveRecord Usage](ACTIVERECORD_USAGE.md) - Detailed ORM guide
- [Internal Routes](MODULE_INTERNAL_ROUTES.md) - HMVC pattern explained
- [SEO Meta Tags](SEO_META_TAGS.md) - SEO tools guide

---

## 🎯 Use Cases

**Perfect for:**
- ✅ RESTful APIs
- ✅ Full-stack web applications  
- ✅ Internal admin panels
- ✅ Microservices
- ✅ Rapid prototyping
- ✅ Learning modern PHP

**Not ideal for:**
- ❌ Extremely simple static sites (use flat HTML)
- ❌ Projects requiring specific frameworks (Laravel, Symfony)

---

## 🏗️ Project Structure

```
my-app/
├── modules/          # Your modules (auto-generated)
│   └── Products/
│       ├── Module.php
│       ├── Controller.php
│       ├── Service.php
│       └── Model.php
├── src/              # Frontend (views, layouts, assets)
│   ├── views/
│   ├── layouts/
│   └── routes/
├── app/              # Framework core
│   ├── Core/
│   └── Palm/
├── config/           # Configuration
│   └── .env
├── database/         # Migrations & seeders
├── public/           # Public assets
└── palm              # CLI tool
```

---

## 🔥 CLI Examples

```bash
# Code Generation
palm make:module Users                    # Full CRUD module
palm make:model Products Product          # Just a model
palm make:component Button                # UI component

# Database
palm make:migration create_orders_table
palm migrate                              # Run migrations
palm db:seed                              # Seed database

# Development
palm serve                                # Dev server + hot reload
palm logs:tail                            # Watch logs in real-time

# Production
palm optimize                             # Cache everything
palm cache:clear                          # Clear all caches
```

---

## 🤝 Contributing

We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch
3. Submit a pull request

---

## 📄 License

MIT License - feel free to use Palm in your projects!

---

## 💬 Community

- **Issues**: [GitHub Issues](https://github.com/your-repo/php-palm/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-repo/php-palm/discussions)

---

<div align="center">
  <strong>Built with ❤️ for PHP developers who value their time</strong>
  <br><br>
  <a href="DOCS.md">📖 Read Full Documentation</a>
</div>
