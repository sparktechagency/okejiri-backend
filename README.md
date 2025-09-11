# Laravel 12 JWT Authentication API Setup

This repository provides a simple and clean implementation of JWT-based authentication in Laravel 12. It also includes optional instructions for enabling Socket.IO for real-time features like chats.

---

## 🚀 How to Setup the Project

Follow the steps below to set up the project on your local machine.

---

#### ✅ Step 1: Clone the Repository

```bash
git clone https://github.com/Sazzat-UGV/JWT-Authentication.git
```

#### ✅ Step 2: Change directory to your project

```bash
cd JWT-Authentication
```

#### ✅ Step 3: Update Composer

```bash
composer update
```

#### ✅ Step 4: Create .env File

Copy the `.env.example` file to `.env`

```bash
cp .env.example .env
```

#### ✅ Step 5: Generate Application Key

```bash
php artisan key:generate
```

#### ✅ Step 6: Generate JWT Secret Key

```bash
php artisan jwt:secret
```


#### ✅ Step 7: Run migrations

```bash
php artisan migrate
```

#### ✅ Step 8: Run seeder

```bash
php artisan db:seed
```

#### ✅ Step 8: Start the server

```bash
php artisan serve
```

## ⚡ Optional: Enable Socket.IO Server (for real-time features)

If you want to enable real-time functionality like chats, follow these extra steps.

#### ✅ Step 1: Initialize Node.js and Install Dependencies

```bash
npm install express socket.io
```

#### ✅ Step 2: Start the socket server

```bash
node server.js
```
