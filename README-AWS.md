# MSW Platform — AWS Serverless Zero-Cost Deployment Guide

A complete, production-grade guide for deploying the **Municipal Solid Waste (MSW) Platform** to Amazon Web Services (AWS) using an **Always Free / 100% Zero-Cost Serverless Architecture**.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [AWS Zero-Cost Breakdown & Limits](#aws-zero-cost-breakdown--limits)
3. [Prerequisites](#prerequisites)
4. [Project Structure](#project-structure)
5. [Step-by-Step Deployment](#step-by-step-deployment)
6. [Mobile & Web Frontend Configuration](#mobile--web-frontend-configuration)
7. [Test Credentials](#test-credentials)
8. [API Verification with cURL](#api-verification-with-curl)
9. [Setting Up AWS Budget Alerts ($0 Safety Net)](#setting-up-aws-budget-alerts-0-safety-net)
10. [Teardown & Clean Up](#teardown--clean-up)
11. [Architectural Differences: Laravel vs AWS Serverless](#architectural-differences-laravel-vs-aws-serverless)

---

## Architecture Overview

The system replaces the monolithic PHP Laravel / MySQL stack with a decoupled, event-driven serverless architecture that stays strictly within the AWS Always Free tier.

```
                      ┌──────────────────────────────────────────────┐
                      │                 CLIENT TIER                  │
                      │  Expo Mobile App (iOS / Android) & Web App   │
                      └──────────────────────┬───────────────────────┘
                                             │
                       HTTPS (Direct / REST) │  Static Assets & Images
                                             │  (GET /storage/*)
                                             ▼               ▼
┌────────────────────────────────────────────────────────────┬───────────────────────────┐
│                      AWS CLOUD                             │      AMAZON CLOUDFRONT    │
│                                                            │   Global CDN Edge Cache   │
│  ┌──────────────────────────────────────────────────────┐  │   (1 TB/mo data transfer) │
│  │             AWS LAMBDA FUNCTION URL                  │  └─────────────┬─────────────┘
│  │          (HTTPS Native Endpoint, Zero Cost)          │                │
│  └──────────────────────────┬───────────────────────────┘                │
│                             │                                            │
│                             ▼                                            │
│  ┌──────────────────────────────────────────────────────┐                │
│  │           AWS LAMBDA (Node.js 20.x ESM)              │                │
│  │  - Fast Router & Input Validation                    │                │
│  │  - Stateless JWT Authentication                      │                │
│  │  - Role-based Access Control (Citizen, Staff, Crew)  │                │
│  │  - Multipart Parser (Busboy) for Image Uploads       │                │
│  └───────┬──────────────────┬───────────────────┬───────┘                │
│          │                  │                   │                        │
│          ▼                  ▼                   ▼                        ▼
│  ┌───────────────┐  ┌───────────────┐   ┌───────────────┐        ┌───────────────┐
│  │AMAZON DYNAMODB│  │   AMAZON S3   │   │  AMAZON SES   │        │   AMAZON S3   │
│  │ (Provisioned  │  │ (Attachments) │   │ (Email alerts │        │ (Static Web)  │
│  │  15/25 RCU/WCU│  │  Uploads &    │   │  on report    │        │  Expo Web PWA │
│  │  Always Free) │  │  Storage      │   │  completion)  │        │  Assets       │
│  └───────────────┘  └───────┬───────┘   └───────────────┘        └───────┬───────┘
│                             │                                            │
│                             └────────────────────┬───────────────────────┘
│                                                  ▼
└────────────────────────────────────────────────────────────────────────────────────────┘
```

### Component Roles

| Service | Role in MSW Platform | Free Tier Category |
| :--- | :--- | :--- |
| **AWS Lambda** | Runs Node.js 20 REST API handlers | Always Free (1M requests/month, 400K GB-s) |
| **Lambda Function URLs** | Direct HTTPS invocation without API Gateway overhead | Included with Lambda ($0.00) |
| **Amazon DynamoDB** | Stores users, categories, reports, assignments, attachments | Always Free (25 RCU, 25 WCU, 25 GB storage) |
| **Amazon S3** | Stores citizen report photos and crew completion evidence | Free Tier (5 GB standard storage, 20K GET, 2K PUT) |
| **Amazon CloudFront** | CDN caching and HTTPS delivery for attachments & web assets | Always Free (1 TB data transfer out, 10M requests) |
| **Amazon SES** | Sends automated email notifications when reports complete | 62,000 emails/month free when triggered from Lambda |

---

## AWS Zero-Cost Breakdown & Limits

To guarantee that this project incurs **$0.00 / month**, all AWS services have been configured within their **Always Free** or **Free Tier** thresholds:

| Resource | AWS Free Tier Allowance | MSW Platform Allocation | Safety Margin | Estimated Cost |
| :--- | :--- | :--- | :--- | :--- |
| **Lambda Invocations** | 1,000,000 requests / mo | ~5,000 – 50,000 / mo | > 95% unused | **$0.00** |
| **Lambda Compute** | 400,000 GB-seconds / mo | ~512 MB × 200ms = 0.1 GB-s / req | > 99% unused | **$0.00** |
| **DynamoDB RCU** | 25 Read Capacity Units | 15 RCU (5 tables × 2 RCU + 5 GSIs × 1 RCU) | 10 RCU spare | **$0.00** |
| **DynamoDB WCU** | 25 Write Capacity Units | 15 WCU (5 tables × 2 WCU + 5 GSIs × 1 WCU) | 10 WCU spare | **$0.00** |
| **DynamoDB Storage**| 25 GB | < 100 MB | > 99% unused | **$0.00** |
| **S3 Storage** | 5 GB standard storage | ~500 MB (compressed photos) | 90% unused | **$0.00** |
| **S3 Operations** | 2,000 PUT / 20,000 GET / mo | Within limits (CloudFront absorbs GETs)| Caching shield | **$0.00** |
| **CloudFront Out** | 1 TB (1,000 GB) / mo | < 2 GB / mo | > 99.8% unused | **$0.00** |
| **Amazon SES** | 62,000 outbound emails / mo | ~50 – 500 emails / mo | > 99% unused | **$0.00** |
| **API Gateway** | *Not Used* (avoiding charges) | Direct Lambda Function URLs | Zero fee | **$0.00** |
| **Total** | | | | **$0.00 / mo** |

> [!IMPORTANT]
> **DynamoDB Capacity Mode**: All DynamoDB tables are configured with **Provisioned Throughput** (2 RCU / 2 WCU for tables, 1 RCU / 1 WCU for GSIs). Do **not** switch them to On-Demand (PAY_PER_REQUEST), as On-Demand billing does not benefit from the 25 RCU/WCU Always Free reservation.

---

## Prerequisites

Before running the deployment script, ensure your environment has:

1. **AWS Account**: An active AWS account with administrative permissions.
2. **AWS CLI v2**: Installed and configured.
   ```bash
   aws --version
   aws configure
   # Enter AWS Access Key ID, Secret Access Key, and Default Region (e.g. ap-southeast-1)
   ```
   Verify authentication:
   ```bash
   aws sts get-caller-identity
   ```
3. **Node.js 18+ or 20+** and **npm**:
   ```bash
   node --version  # Should be >= 18.0.0
   npm --version
   ```
4. **Command Line Utilities**: `bash`, `curl`, `jq`, `zip`.
   ```bash
   # On Ubuntu / Debian:
   sudo apt-get install -y curl jq zip
   ```

---

## Project Structure

```
MSW-Platform/
├── backend-serverless/           # AWS Serverless Lambda backend
│   ├── .env.example              # Environment variables template
│   ├── deploy.sh                 # 1-click automated deployment script
│   ├── teardown.sh               # 1-click resource deletion script
│   ├── setup-dynamodb.mjs        # DynamoDB table provisioning script
│   ├── seed.mjs                  # Database seeder (categories + test users)
│   ├── index.mjs                 # Lambda Function URL entrypoint
│   ├── package.json              # Node.js ES module dependencies
│   ├── lib/
│   │   ├── auth.mjs              # JWT creation, verification & bcrypt hashing
│   │   ├── db.mjs                # DynamoDB DocumentClient wrapper
│   │   ├── email.mjs             # AWS SES email notification dispatch
│   │   ├── multipart.mjs         # Busboy multipart form parser for S3 uploads
│   │   ├── router.mjs            # Lightweight HTTP router with regex path matching
│   │   └── s3.mjs                # S3 PutObject and URL generation
│   └── handlers/
│       ├── auth.mjs              # /api/register, /api/login, /api/logout, /api/user
│       ├── categories.mjs        # /api/categories
│       └── reports.mjs           # /api/reports (CRUD, assign, status update, attachments)
├── mobile/                       # Expo React Native App (iOS, Android & Web)
│   ├── .env.example              # Mobile environment variables
│   ├── app/                      # Expo Router screens
│   │   ├── index.tsx             # Auth router (redirects to role dashboard)
│   │   └── (app)/...             # Citizen, Staff, and Crew views
│   └── src/services/api.ts       # Axios client targeting Lambda API
├── README-AWS.md                 # This deployment guide
└── README.md                     # Original project documentation
```

---

## Step-by-Step Deployment

The repository includes a fully automated deployment script (`deploy.sh`) that provisions IAM roles, S3 buckets, DynamoDB tables, seeds initial data, packages the Lambda bundle, and activates the Lambda Function URL.

### Step 1: Navigate to the Serverless Backend Directory

```bash
cd /home/eka/Documents/projects/MSW-Platform/backend-serverless
```

### Step 2: Configure Environment Variables

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Review or adjust `.env` parameters:

```ini
# Secret key used for signing HMAC-SHA256 JWT tokens
JWT_SECRET=your-custom-secure-random-jwt-secret

# AWS Region to deploy all resources (e.g., ap-southeast-1, us-east-1)
AWS_REGION=ap-southeast-1

# DynamoDB table prefix (default: msw)
TABLE_PREFIX=msw

# SES Verified Sender Email (for notification emails)
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

> [!NOTE]
> If `JWT_SECRET` is left as default, `deploy.sh` will automatically generate a secure 64-character random string. S3 bucket names are automatically scoped to your AWS Account ID (e.g., `msw-attachments-<ACCOUNT_ID>`) to prevent naming conflicts.

### Step 3: Run the Automated Deployment Script

Make sure `deploy.sh` is executable and execute it:

```bash
chmod +x deploy.sh teardown.sh
./deploy.sh
```

### What `deploy.sh` Performs Automatically:

1. **Prerequisite Check**: Validates `aws`, `node`, `npm`, and `zip`.
2. **Account Detection**: Retrieves AWS Account ID and verifies IAM permissions.
3. **S3 Bucket Creation**:
   - Creates the `msw-attachments-<ACCOUNT_ID>` bucket with public read policy (or CloudFront OAC).
   - Configures CORS on S3 so mobile/web apps can directly fetch photos.
4. **DynamoDB Provisioning (`setup-dynamodb.mjs`)**:
   - `msw-users` (2 RCU / 2 WCU) + `email-index` GSI (1 RCU / 1 WCU)
   - `msw-categories` (2 RCU / 2 WCU)
   - `msw-reports` (2 RCU / 2 WCU) + `status-createdAt-index` GSI + `userId-index` GSI
   - `msw-attachments` (2 RCU / 2 WCU) + `reportId-index` GSI
   - `msw-report-crew` (2 RCU / 2 WCU) + `crewUserId-index` GSI
   - *Total capacity:* 15 RCU / 15 WCU (safely under 25 Always Free limit).
5. **Database Seeding (`seed.mjs`)**:
   - Inserts 10 standard waste categories in Indonesian.
   - Inserts 3 pre-configured demo users (`citizen`, `staff`, `crew`) with bcrypt-hashed passwords.
6. **Lambda Package & IAM Role**:
   - Creates `msw-lambda-role` with DynamoDB, S3, SES, and CloudWatch Logs permissions.
   - Installs production npm dependencies and creates `deployment.zip`.
   - Creates or updates the `msw-api` Lambda function (Node.js 20.x, 512 MB memory, 15s timeout).
7. **Function URL & CORS Setup**:
   - Enables native Lambda Function URL with auth type `NONE`.
   - Grants public `lambda:InvokeFunctionUrl` permission.
   - Configures CORS headers: `Access-Control-Allow-Origin: *`, allowed methods, and headers (`Authorization`, `Content-Type`).

At the conclusion of the script, your live API endpoint will be displayed:
```
=========================================================
  MSW Platform Serverless Backend Deployed Successfully!
=========================================================

  API Function URL : https://xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.lambda-url.ap-southeast-1.on.aws/
  API Health Check : https://xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.lambda-url.ap-southeast-1.on.aws/api/health
  Attachments S3   : msw-attachments-123456789012
=========================================================
```

---

## Mobile & Web Frontend Configuration

The frontend is built using **React Native with Expo** and supports Android, iOS, and Web (PWA).

### Step 1: Configure Mobile Environment

Navigate to the `mobile` folder:

```bash
cd /home/eka/Documents/projects/MSW-Platform/mobile
```

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Set `EXPO_PUBLIC_API_URL` to your Lambda Function URL with the `/api` prefix:

```ini
EXPO_PUBLIC_API_URL=https://xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.lambda-url.ap-southeast-1.on.aws/api
```

### Step 2: Install Dependencies and Run

```bash
# Install dependencies
npm install

# Start Expo development server
npx expo start
```

Press:
- `w` to open in your desktop/mobile web browser.
- `a` to run on an connected Android device or emulator.
- Scan the QR code with the Expo Go app on your phone.

---

## Test Credentials

The database is pre-seeded with three accounts covering all three platform roles:

| Role | Email | Password | Allowed Capabilities |
| :--- | :--- | :--- | :--- |
| **Citizen** | `citizen@test.com` | `Password123!` | Create reports, attach photos, view personal reports, view city report map |
| **Staff** | `staff@test.com` | `Password123!` | Review citizen reports, inspect location, assign reports to cleaning crews |
| **Crew** | `crew@test.com` | `Password123!` | View assigned tasks, mark `in_progress`, mark `completed` with proof photo |

---

## API Verification with cURL

You can test the entire platform lifecycle via `curl`. Replace `LAMBDA_URL` with your actual Function URL:

```bash
LAMBDA_URL="https://YOUR_LAMBDA_FUNCTION_URL"
```

### 1. Health Check
```bash
curl -s "${LAMBDA_URL}/api/health" | jq .
```
Expected response:
```json
{
  "status": "ok",
  "timestamp": "2026-09-20T11:00:00.000Z",
  "service": "msw-platform-api"
}
```

### 2. Fetch Waste Categories
```bash
curl -s "${LAMBDA_URL}/api/categories" | jq .
```

### 3. Citizen Login & Token Extraction
```bash
CITIZEN_RES=$(curl -s -X POST "${LAMBDA_URL}/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"citizen@test.com","password":"Password123!"}')

CITIZEN_TOKEN=$(echo $CITIZEN_RES | jq -r '.token')
echo "Citizen Token: $CITIZEN_TOKEN"
```

### 4. Create a New Waste Report (Citizen)
```bash
# Create report (JSON payload)
REPORT_RES=$(curl -s -X POST "${LAMBDA_URL}/api/reports" \
  -H "Authorization: Bearer ${CITIZEN_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "category_id": "1",
    "description": "Tumpukan sampah plastik liar di pinggir jalan raya.",
    "latitude": -6.2088,
    "longitude": 106.8456
  }')

echo $REPORT_RES | jq .
REPORT_ID=$(echo $REPORT_RES | jq -r '.data.id')
echo "Created Report ID: $REPORT_ID"
```

### 5. Create a Report with Photo (Multipart Upload)
```bash
curl -s -X POST "${LAMBDA_URL}/api/reports" \
  -H "Authorization: Bearer ${CITIZEN_TOKEN}" \
  -F "category_id=1" \
  -F "description=Sampah liar dengan bukti foto." \
  -F "latitude=-6.2088" \
  -F "longitude=106.8456" \
  -F "photos[]=@/path/to/test-image.jpg" | jq .
```

### 6. Staff Login & Report Assignment
```bash
# Login as staff
STAFF_RES=$(curl -s -X POST "${LAMBDA_URL}/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"staff@test.com","password":"Password123!"}')

STAFF_TOKEN=$(echo $STAFF_RES | jq -r '.token')

# Find crew user ID (or use Joko Prasetyo)
# Assign report to crew
curl -s -X POST "${LAMBDA_URL}/api/reports/${REPORT_ID}/assign" \
  -H "Authorization: Bearer ${STAFF_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "crew_ids": ["CREW_USER_UUID"]
  }' | jq .
```

### 7. Crew Login & Update Status to In-Progress
```bash
# Login as crew
CREW_RES=$(curl -s -X POST "${LAMBDA_URL}/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"crew@test.com","password":"Password123!"}')

CREW_TOKEN=$(echo $CREW_RES | jq -r '.token')

# View assigned tasks
curl -s -X GET "${LAMBDA_URL}/api/crew/reports" \
  -H "Authorization: Bearer ${CREW_TOKEN}" | jq .

# Mark report in_progress
curl -s -X PATCH "${LAMBDA_URL}/api/crew/reports/${REPORT_ID}/status" \
  -H "Authorization: Bearer ${CREW_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"status":"in_progress"}' | jq .
```

### 8. Crew Complete Task with Resolution Photo
```bash
curl -s -X PATCH "${LAMBDA_URL}/api/crew/reports/${REPORT_ID}/status" \
  -H "Authorization: Bearer ${CREW_TOKEN}" \
  -F "status=completed" \
  -F "photo=@/path/to/clean-proof.jpg" | jq .
```

---

## Setting Up AWS Budget Alerts ($0 Safety Net)

To guarantee that you never receive an unexpected bill if your usage exceeds normal levels, create an **AWS Budget Alert** set to trigger at **$1.00**.

### Method 1: Via AWS CLI (Quickest)

1. Create a budget definition file `budget.json`:
   ```json
   {
     "BudgetLimit": {
       "Amount": "1.00",
       "Unit": "USD"
     },
     "BudgetName": "ZeroCost-MSW-SafetyNet",
     "BudgetType": "COST",
     "CostFilters": {},
     "CostTypes": {
       "IncludeCredit": false,
       "IncludeDiscount": true,
       "IncludeOtherSubscription": true,
       "IncludeRecurring": true,
       "IncludeRefund": false,
       "IncludeSubscription": true,
       "IncludeSupport": true,
       "IncludeTax": true,
       "IncludeUpfront": true,
       "UseBlended": false
     },
     "TimeUnit": "MONTHLY"
   }
   ```

2. Create notifications file `notifications.json` (replace `YOUR_EMAIL@example.com`):
   ```json
   [
     {
       "Notification": {
         "ComparisonOperator": "GREATER_THAN",
         "NotificationType": "ACTUAL",
         "Threshold": 80,
         "ThresholdType": "PERCENTAGE"
       },
       "Subscribers": [
         {
           "Address": "YOUR_EMAIL@example.com",
           "SubscriptionType": "EMAIL"
         }
       ]
     }
   ]
   ```

3. Execute the AWS CLI command:
   ```bash
   ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
   aws budgets create-budget \
     --account-id "${ACCOUNT_ID}" \
     --budget file://budget.json \
     --notifications-with-subscribers file://notifications.json
   ```

### Method 2: Via AWS Management Console

1. Open the [AWS Budgets Console](https://console.aws.amazon.com/billing/home#/budgets).
2. Click **Create budget**.
3. Under *Budget setup*, select **Zero spend budget** (or *Cost budget* with a limit of `$1.00`).
4. Set email recipient to your personal address.
5. Click **Create budget**. AWS will instantly notify you if any service accumulates non-zero spend.

---

## Teardown & Clean Up

When you are finished testing or want to remove all resources created on AWS, run the automated `teardown.sh` script:

```bash
cd /home/eka/Documents/projects/MSW-Platform/backend-serverless
./teardown.sh
```

### What `teardown.sh` Removes:
- Deletes the Lambda function `msw-api` and its Function URL.
- Empties and deletes the S3 buckets `msw-attachments-<ACCOUNT_ID>` and `msw-web-<ACCOUNT_ID>`.
- Deletes all 5 DynamoDB tables (`msw-users`, `msw-categories`, `msw-reports`, `msw-attachments`, `msw-report-crew`).
- Deletes CloudWatch log groups (`/aws/lambda/msw-api`).
- Detaches policies and deletes the IAM role `msw-lambda-role`.
- Deletes CloudFront distribution (if deployed).

Your AWS account is returned to a clean, zero-resource state.

---

## Architectural Differences: Laravel vs AWS Serverless

| Dimension | Original Laravel Backend | AWS Serverless Backend |
| :--- | :--- | :--- |
| **Runtime Environment** | PHP 8.2 + Apache / Nginx on persistent VM | Node.js 20.x ES Modules on AWS Lambda (on-demand) |
| **Database** | Relational MySQL (InnoDB, Foreign Keys, Joins) | Amazon DynamoDB NoSQL (Key-Value & Document model) |
| **Database Scaling & Cost** | Requires always-on RDS / EC2 instance (~$15-$30/mo) | Provisioned Capacity (15 RCU/WCU) = **$0.00** Always Free |
| **Primary Keys** | Auto-incrementing integers (`1, 2, 3...`) | Universally Unique Identifiers (UUID v4 strings) |
| **Authentication** | Laravel Sanctum (stateful DB `personal_access_tokens` table) | Stateless HMAC-SHA256 JWT tokens (no DB lookup per request) |
| **File Storage** | Local filesystem (`storage/app/public/`) | Amazon S3 bucket with direct public/CloudFront CDN URLs |
| **File URL Resolution** | Mobile app had to prefix with `WEB_APP_URL + '/storage/'` | Backend provides full, direct HTTPS URLs from S3 / CloudFront |
| **Email Notifications** | Laravel Mailable via local SMTP / Mailhog | Amazon SES via AWS SDK v3 with graceful fallback logging |
| **Desktop Web Experience** | Server-side rendered Blade views (`.blade.php`) | Universal Expo Web React application (same code as mobile) |
| **API Endpoints** | Laravel Route collection (`routes/api.php`) | Pattern-matched event routing (`lib/router.mjs`) on Function URL |
| **Cold Starts & Idle Cost** | Idle VM incurs 100% hourly cost; no cold starts | Zero idle cost ($0); sub-second cold starts with ES modules |
| **Deployment** | SSH + Composer + Artisan migrate + Docker | Single-command `./deploy.sh` script via AWS CLI |

---

## Conclusion & Support

With this architecture, the Municipal Solid Waste Platform runs continuously in the cloud with zero operational costs while demonstrating high scalability, high availability, and modern cloud-native serverless design patterns.

For questions or issues, please check the project issues or consult the [AWS Free Tier Documentation](https://aws.amazon.com/free/).
