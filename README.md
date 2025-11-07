# EducAid - Educational Financial Assistance Platform

> **A comprehensive web-based system for managing educational financial aid in General Trias, Cavite**

[![PHP Version](https://img.shields.io/badge/PHP-8.x-blue.svg)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-316192.svg)](https://www.postgresql.org/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-purple.svg)](https://getbootstrap.com/)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)](LICENSE)

---

## 🚀 Quick Start

### Prerequisites
- **XAMPP** (Apache 2.4.58, PostgreSQL, PHP 8.x)
- **Composer** (for PHPMailer dependencies)
- **Node.js** (optional, for development tools)

### Local Setup
```bash
# 1. Clone the repository
git clone https://github.com/PseudoHat/EducAid.git
cd EducAid

# 2. Install dependencies
composer install

# 3. Configure environment
cp config/.env.example config/.env
# Edit config/.env with your database credentials

# 4. Start XAMPP
# - Start Apache
# - Start PostgreSQL

# 5. Access the application
http://localhost/EducAid/website/
```

---

## 📚 Documentation

**All documentation has been moved to the `/docs` folder for better organization!**

### 📖 Essential Guides
- **[Complete Documentation Index](docs/INDEX.md)** - Navigate all 109 documentation files
- **[Deployment Guide](docs/README_DEPLOY.md)** - Railway deployment instructions
- **[Security Guide](docs/SECURITY_HEADERS_IMPLEMENTATION.md)** - Security implementation
- **[Testing Checklist](docs/TESTING_CHECKLIST.md)** - QA testing procedures

### 🎯 Quick Links
| Category | Link |
|----------|------|
| 🔐 Security | [docs/SECURITY_QUICK_REFERENCE.md](docs/SECURITY_QUICK_REFERENCE.md) |
| 🎨 Theming | [docs/THEME_GENERATOR_SIMPLE_GUIDE.md](docs/THEME_GENERATOR_SIMPLE_GUIDE.md) |
| 🔔 Notifications | [docs/STUDENT_NOTIFICATION_SYSTEM_GUIDE.md](docs/STUDENT_NOTIFICATION_SYSTEM_GUIDE.md) |
| 📄 Documents | [docs/DOCUMENT_VALIDATION_COMPARISON.md](docs/DOCUMENT_VALIDATION_COMPARISON.md) |
| 🏫 Multi-Municipality | [docs/MULTI_MUNICIPALITY_IMPLEMENTATION_GUIDE.md](docs/MULTI_MUNICIPALITY_IMPLEMENTATION_GUIDE.md) |
| 🛠️ Debugging | [docs/DEBUGGING_GUIDE.md](docs/DEBUGGING_GUIDE.md) |

---

## 🏗️ Project Structure

```
EducAid/
├── assets/              # CSS, JS, images, fonts
├── config/              # Configuration files (.env, database)
├── docs/                # 📚 All documentation (109 files)
├── includes/            # PHP components (headers, sidebars, utilities)
├── modules/             # Feature modules (admin, student, super_admin)
├── phpmailer/           # Email library
├── services/            # API endpoints & services
├── temp_files/          # Temporary uploads
├── website/             # Public-facing pages
├── router.php           # Main entry point
└── README.md            # This file
```

---

## 🎯 Key Features

### For Students
- ✅ Online application submission
- ✅ Document upload with OCR validation
- ✅ Real-time application tracking
- ✅ Slot booking system
- ✅ Email & in-app notifications
- ✅ Mobile-responsive interface

### For Admins
- ✅ Application review & validation
- ✅ Document verification (6-check system)
- ✅ Distribution control & scheduling
- ✅ Multi-municipality support
- ✅ Real-time notifications
- ✅ Audit logging

### For Super Admins
- ✅ Multi-municipality management
- ✅ CMS for login/footer/content
- ✅ Theme generator (colors, logos)
- ✅ Bulk operations (logo upload, etc.)
- ✅ System-wide settings
- ✅ Security monitoring

---

## 🔒 Security Features

- **Session Management**: Idle timeout (30 min), absolute timeout (8 hours)
- **HTTP Security Headers**: HSTS, CSP, X-Frame-Options, etc.
- **CSRF Protection**: Token-based request validation
- **reCAPTCHA v2**: Bot protection on public forms
- **Multi-Account Prevention**: Duplicate detection system
- **Audit Logging**: All critical actions logged
- **Password Security**: Strong validation rules

👉 See [docs/SECURITY_HEADERS_IMPLEMENTATION.md](docs/SECURITY_HEADERS_IMPLEMENTATION.md) for details

---

## 🚀 Deployment

### Railway (Production)
```bash
# Push to main branch triggers auto-deploy
git add .
git commit -m "Your changes"
git push origin main

# Configure environment variables in Railway dashboard
# See docs/RAILWAY_ENV_SETUP.md for variable list
```

### Environment Variables
Required variables in Railway:
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`
- `SESSION_IDLE_TIMEOUT_MINUTES`, `SESSION_ABSOLUTE_TIMEOUT_HOURS`

👉 Full list: [docs/RAILWAY_ENV_SETUP.md](docs/RAILWAY_ENV_SETUP.md)

---

## 🧪 Testing

### Run Tests
```bash
# Manual testing checklist
# See docs/TESTING_CHECKLIST.md

# Test security headers
curl -I https://your-domain.com

# Test session timeout
# See docs/SESSION_TIMEOUT_IMPLEMENTATION.md
```

---

## 📊 Tech Stack

| Technology | Version | Purpose |
|------------|---------|---------|
| PHP | 8.x | Backend logic |
| PostgreSQL | 15 | Database |
| Bootstrap | 5.3 | UI framework |
| PHPMailer | 6.x | Email service |
| reCAPTCHA | v2 | Bot protection |
| Tesseract.js | 4.x | OCR processing |
| Railway | - | Hosting platform |

---

## 🤝 Contributing

1. Create a feature branch: `git checkout -b feature/your-feature`
2. Commit changes: `git commit -m "Add your feature"`
3. Push to branch: `git push origin feature/your-feature`
4. Open a Pull Request

**Important**: All new features must include documentation in `/docs` folder!

---

## 📝 License

This project is proprietary software developed for the Municipality of General Trias.

---

## 📧 Support

- **Documentation**: [docs/INDEX.md](docs/INDEX.md)
- **Debugging**: [docs/DEBUGGING_GUIDE.md](docs/DEBUGGING_GUIDE.md)
- **Security Issues**: Contact system administrator

---

## 📈 Recent Updates

- ✅ **Session Timeout System** (Nov 8, 2025) - See [docs/SESSION_TIMEOUT_IMPLEMENTATION.md](docs/SESSION_TIMEOUT_IMPLEMENTATION.md)
- ✅ **Security Headers** (Nov 8, 2025) - See [docs/SECURITY_HEADERS_IMPLEMENTATION.md](docs/SECURITY_HEADERS_IMPLEMENTATION.md)
- ✅ **Documentation Reorganization** (Nov 8, 2025) - All docs moved to `/docs` folder

---

**Made with ❤️ for General Trias, Cavite**
