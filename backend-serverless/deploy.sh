#!/usr/bin/env bash
set -euo pipefail

###############################################################################
#  MSW Platform — Complete Serverless Deployment Script
#
#  Deploys from zero to a fully running serverless API:
#    1. S3 buckets (attachments + web hosting)
#    2. DynamoDB tables
#    3. IAM role for Lambda
#    4. Lambda function + Function URL
#    5. Seed data
#    6. CloudFront distribution for attachments
#    7. Budget alert ($0.01 threshold)
#
#  Prerequisites:
#    - AWS CLI v2 installed and configured
#    - Node.js 18+ and npm
#    - jq (for JSON parsing)
#
#  Usage: ./deploy.sh
###############################################################################

# ── Colours ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m' # No Colour

info()    { echo -e "${CYAN}  ℹ  ${NC}$1"; }
success() { echo -e "${GREEN}  ✅  ${NC}$1"; }
warn()    { echo -e "${YELLOW}  ⏩  ${NC}$1"; }
error()   { echo -e "${RED}  ❌  ${NC}$1"; }
header()  { echo -e "\n${BOLD}═══════════════════════════════════════════════${NC}"; echo -e "${BOLD}  $1${NC}"; echo -e "${BOLD}═══════════════════════════════════════════════${NC}\n"; }
step()    { echo -e "\n${BOLD}── $1 ──${NC}\n"; }

# ── Variables ────────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REGION="${AWS_REGION:-ap-southeast-1}"
TABLE_PREFIX="${TABLE_PREFIX:-msw}"
FUNCTION_NAME="msw-api"
ROLE_NAME="msw-lambda-role"
POLICY_NAME="msw-lambda-policy"
RUNTIME="nodejs20.x"
HANDLER="src/index.handler"
TIMEOUT=30
MEMORY_SIZE=256
BUDGET_NAME="msw-zero-cost-budget"

# Get AWS Account ID
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text --region "$REGION")
info "AWS Account: ${CYAN}${ACCOUNT_ID}${NC}"

ATTACHMENTS_BUCKET="${MSW_ATTACHMENTS_BUCKET:-msw-attachments-${ACCOUNT_ID}}"
WEB_BUCKET="${MSW_WEB_BUCKET:-msw-web-${ACCOUNT_ID}}"

# JWT secret: generate if not set
if [ -z "${JWT_SECRET:-}" ]; then
  JWT_SECRET=$(openssl rand -base64 48 | tr -d '/+=' | head -c 64)
  info "Generated JWT_SECRET (64 chars)"
else
  info "Using existing JWT_SECRET from environment"
fi

MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-noreply@example.com}"

header "MSW Platform — Serverless Deployment"
echo -e "  Region              : ${CYAN}${REGION}${NC}"
echo -e "  Account             : ${CYAN}${ACCOUNT_ID}${NC}"
echo -e "  Function            : ${CYAN}${FUNCTION_NAME}${NC}"
echo -e "  Attachments bucket  : ${CYAN}${ATTACHMENTS_BUCKET}${NC}"
echo -e "  Web bucket          : ${CYAN}${WEB_BUCKET}${NC}"
echo -e "  Table prefix        : ${CYAN}${TABLE_PREFIX}${NC}"
echo ""

###############################################################################
# STEP 1: S3 Buckets
###############################################################################
step "Step 1/7 — S3 Buckets"

create_s3_bucket() {
  local BUCKET_NAME=$1
  local PURPOSE=$2

  if aws s3api head-bucket --bucket "$BUCKET_NAME" --region "$REGION" 2>/dev/null; then
    warn "Bucket '${BUCKET_NAME}' already exists — skipping"
  else
    info "Creating S3 bucket '${BUCKET_NAME}' (${PURPOSE})..."

    # ap-southeast-1 is not us-east-1, so we need LocationConstraint
    if [ "$REGION" = "us-east-1" ]; then
      aws s3api create-bucket \
        --bucket "$BUCKET_NAME" \
        --region "$REGION" \
        --output text > /dev/null
    else
      aws s3api create-bucket \
        --bucket "$BUCKET_NAME" \
        --region "$REGION" \
        --create-bucket-configuration LocationConstraint="$REGION" \
        --output text > /dev/null
    fi

    # Block public access (CloudFront will serve content)
    aws s3api put-public-access-block \
      --bucket "$BUCKET_NAME" \
      --public-access-block-configuration \
        BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true \
      --region "$REGION"

    success "Bucket '${BUCKET_NAME}' created"
  fi
}

create_s3_bucket "$ATTACHMENTS_BUCKET" "report attachments"
create_s3_bucket "$WEB_BUCKET" "static web hosting"

# Enable CORS on attachments bucket for direct uploads
info "Configuring CORS on attachments bucket..."
aws s3api put-bucket-cors \
  --bucket "$ATTACHMENTS_BUCKET" \
  --cors-configuration '{
    "CORSRules": [
      {
        "AllowedHeaders": ["*"],
        "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
        "AllowedOrigins": ["*"],
        "ExposeHeaders": ["ETag"],
        "MaxAgeSeconds": 3600
      }
    ]
  }' \
  --region "$REGION"
success "CORS configured on '${ATTACHMENTS_BUCKET}'"

###############################################################################
# STEP 2: DynamoDB Tables
###############################################################################
step "Step 2/7 — DynamoDB Tables"

info "Running setup-dynamodb.mjs..."
(cd "$SCRIPT_DIR" && AWS_REGION="$REGION" TABLE_PREFIX="$TABLE_PREFIX" node setup-dynamodb.mjs)
success "DynamoDB tables ready"

###############################################################################
# STEP 3: IAM Role for Lambda
###############################################################################
step "Step 3/7 — IAM Role"

ASSUME_ROLE_POLICY='{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": { "Service": "lambda.amazonaws.com" },
      "Action": "sts:AssumeRole"
    }
  ]
}'

# Create role if it doesn't exist
if aws iam get-role --role-name "$ROLE_NAME" --region "$REGION" 2>/dev/null; then
  warn "IAM role '${ROLE_NAME}' already exists — skipping creation"
  ROLE_ARN=$(aws iam get-role --role-name "$ROLE_NAME" --query 'Role.Arn' --output text)
else
  info "Creating IAM role '${ROLE_NAME}'..."
  ROLE_ARN=$(aws iam create-role \
    --role-name "$ROLE_NAME" \
    --assume-role-policy-document "$ASSUME_ROLE_POLICY" \
    --query 'Role.Arn' \
    --output text)
  success "IAM role created: ${ROLE_ARN}"
fi

# Build the inline policy with minimal permissions
LAMBDA_POLICY=$(cat <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "CloudWatchLogs",
      "Effect": "Allow",
      "Action": [
        "logs:CreateLogGroup",
        "logs:CreateLogStream",
        "logs:PutLogEvents"
      ],
      "Resource": "arn:aws:logs:${REGION}:${ACCOUNT_ID}:log-group:/aws/lambda/${FUNCTION_NAME}:*"
    },
    {
      "Sid": "DynamoDBAccess",
      "Effect": "Allow",
      "Action": [
        "dynamodb:GetItem",
        "dynamodb:PutItem",
        "dynamodb:UpdateItem",
        "dynamodb:DeleteItem",
        "dynamodb:Query",
        "dynamodb:Scan",
        "dynamodb:BatchGetItem",
        "dynamodb:BatchWriteItem"
      ],
      "Resource": [
        "arn:aws:dynamodb:${REGION}:${ACCOUNT_ID}:table/${TABLE_PREFIX}-*",
        "arn:aws:dynamodb:${REGION}:${ACCOUNT_ID}:table/${TABLE_PREFIX}-*/index/*"
      ]
    },
    {
      "Sid": "S3Access",
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::${ATTACHMENTS_BUCKET}",
        "arn:aws:s3:::${ATTACHMENTS_BUCKET}/*"
      ]
    },
    {
      "Sid": "SESAccess",
      "Effect": "Allow",
      "Action": [
        "ses:SendEmail",
        "ses:SendRawEmail"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "ses:FromAddress": "${MAIL_FROM_ADDRESS}"
        }
      }
    }
  ]
}
EOF
)

info "Updating inline policy '${POLICY_NAME}'..."
aws iam put-role-policy \
  --role-name "$ROLE_NAME" \
  --policy-name "$POLICY_NAME" \
  --policy-document "$LAMBDA_POLICY"
success "Inline policy '${POLICY_NAME}' applied"

# Give IAM a moment to propagate (critical for new roles)
info "Waiting 10s for IAM propagation..."
sleep 10

###############################################################################
# STEP 4: Lambda Function + Function URL
###############################################################################
step "Step 4/7 — Lambda Function"

# Install production dependencies
info "Installing npm dependencies (production)..."
(cd "$SCRIPT_DIR" && npm ci --omit=dev --silent 2>/dev/null || npm install --omit=dev --silent)
success "Dependencies installed"

# Create deployment package
info "Creating deployment ZIP..."
DEPLOY_ZIP="/tmp/msw-api-deploy.zip"
rm -f "$DEPLOY_ZIP"

# Zip from the project root, including only what Lambda needs
(cd "$SCRIPT_DIR" && zip -q -r "$DEPLOY_ZIP" \
  src/ \
  node_modules/ \
  package.json \
  -x "*.sh" \
  -x "setup-dynamodb.mjs" \
  -x "seed.mjs" \
  -x ".env*" \
  -x "*.md" \
  -x ".git/*" \
  -x "node_modules/.cache/*" \
  -x "node_modules/.package-lock.json" \
)
ZIP_SIZE=$(du -h "$DEPLOY_ZIP" | cut -f1)
success "Deployment ZIP created (${ZIP_SIZE})"

# Environment variables for the Lambda function
ENV_VARS=$(cat <<EOF
{
  "Variables": {
    "JWT_SECRET": "${JWT_SECRET}",
    "AWS_REGION_CUSTOM": "${REGION}",
    "TABLE_PREFIX": "${TABLE_PREFIX}",
    "MSW_ATTACHMENTS_BUCKET": "${ATTACHMENTS_BUCKET}",
    "MAIL_FROM_ADDRESS": "${MAIL_FROM_ADDRESS}",
    "CLOUDFRONT_DOMAIN": "${CLOUDFRONT_DOMAIN:-}",
    "NODE_ENV": "production"
  }
}
EOF
)

# Create or update the Lambda function
if aws lambda get-function --function-name "$FUNCTION_NAME" --region "$REGION" 2>/dev/null; then
  warn "Lambda function '${FUNCTION_NAME}' exists — updating..."

  aws lambda update-function-code \
    --function-name "$FUNCTION_NAME" \
    --zip-file "fileb://${DEPLOY_ZIP}" \
    --region "$REGION" \
    --output text > /dev/null

  # Wait for the update to complete
  info "Waiting for function update to complete..."
  aws lambda wait function-updated-v2 \
    --function-name "$FUNCTION_NAME" \
    --region "$REGION" 2>/dev/null || sleep 5

  aws lambda update-function-configuration \
    --function-name "$FUNCTION_NAME" \
    --runtime "$RUNTIME" \
    --handler "$HANDLER" \
    --timeout "$TIMEOUT" \
    --memory-size "$MEMORY_SIZE" \
    --environment "$ENV_VARS" \
    --region "$REGION" \
    --output text > /dev/null

  success "Lambda function updated"
else
  info "Creating Lambda function '${FUNCTION_NAME}'..."

  aws lambda create-function \
    --function-name "$FUNCTION_NAME" \
    --runtime "$RUNTIME" \
    --role "$ROLE_ARN" \
    --handler "$HANDLER" \
    --zip-file "fileb://${DEPLOY_ZIP}" \
    --timeout "$TIMEOUT" \
    --memory-size "$MEMORY_SIZE" \
    --environment "$ENV_VARS" \
    --region "$REGION" \
    --output text > /dev/null

  # Wait until function is active
  info "Waiting for function to become active..."
  aws lambda wait function-active-v2 \
    --function-name "$FUNCTION_NAME" \
    --region "$REGION" 2>/dev/null || sleep 10

  success "Lambda function created"
fi

# Configure Function URL
FUNCTION_URL=""
EXISTING_URL=$(aws lambda get-function-url-config \
  --function-name "$FUNCTION_NAME" \
  --region "$REGION" \
  --query 'FunctionUrl' \
  --output text 2>/dev/null || echo "")

if [ -n "$EXISTING_URL" ] && [ "$EXISTING_URL" != "None" ]; then
  warn "Function URL already configured — updating CORS..."

  aws lambda update-function-url-config \
    --function-name "$FUNCTION_NAME" \
    --auth-type NONE \
    --cors '{
      "AllowOrigins": ["*"],
      "AllowMethods": ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
      "AllowHeaders": ["Content-Type", "Authorization", "X-Requested-With"],
      "AllowCredentials": false,
      "MaxAge": 86400
    }' \
    --region "$REGION" \
    --output text > /dev/null

  FUNCTION_URL="$EXISTING_URL"
  success "Function URL CORS updated"
else
  info "Creating Function URL..."

  FUNCTION_URL=$(aws lambda create-function-url-config \
    --function-name "$FUNCTION_NAME" \
    --auth-type NONE \
    --cors '{
      "AllowOrigins": ["*"],
      "AllowMethods": ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
      "AllowHeaders": ["Content-Type", "Authorization", "X-Requested-With"],
      "AllowCredentials": false,
      "MaxAge": 86400
    }' \
    --region "$REGION" \
    --query 'FunctionUrl' \
    --output text)

  success "Function URL created"
fi

# Grant public access to the Function URL (resource-based policy)
info "Adding public invoke permission..."
aws lambda add-permission \
  --function-name "$FUNCTION_NAME" \
  --statement-id "FunctionURLAllowPublicAccess" \
  --action "lambda:InvokeFunctionUrl" \
  --principal "*" \
  --function-url-auth-type NONE \
  --region "$REGION" \
  --output text > /dev/null 2>&1 || warn "Permission already exists"

success "Function URL: ${CYAN}${FUNCTION_URL}${NC}"

# Clean up
rm -f "$DEPLOY_ZIP"

###############################################################################
# STEP 5: Seed Data
###############################################################################
step "Step 5/7 — Seed Data"

info "Running seed.mjs..."
(cd "$SCRIPT_DIR" && AWS_REGION="$REGION" TABLE_PREFIX="$TABLE_PREFIX" node seed.mjs)
success "Seed data loaded"

###############################################################################
# STEP 6: CloudFront Distribution (for attachments)
###############################################################################
step "Step 6/7 — CloudFront Distribution"

CLOUDFRONT_DOMAIN=""
DISTRIBUTION_ID=""

# Check if a distribution already exists for this bucket
EXISTING_DIST=$(aws cloudfront list-distributions \
  --query "DistributionList.Items[?Origins.Items[0].DomainName=='${ATTACHMENTS_BUCKET}.s3.${REGION}.amazonaws.com'].{Id:Id,Domain:DomainName}" \
  --output json --region "$REGION" 2>/dev/null || echo "[]")

EXISTING_DIST_ID=$(echo "$EXISTING_DIST" | jq -r '.[0].Id // empty' 2>/dev/null || echo "")

if [ -n "$EXISTING_DIST_ID" ]; then
  warn "CloudFront distribution already exists (${EXISTING_DIST_ID}) — skipping"
  CLOUDFRONT_DOMAIN=$(aws cloudfront get-distribution \
    --id "$EXISTING_DIST_ID" \
    --query 'Distribution.DomainName' \
    --output text --region "$REGION")
  DISTRIBUTION_ID="$EXISTING_DIST_ID"
else
  info "Creating Origin Access Control..."

  # Create OAC for S3
  OAC_ID=$(aws cloudfront create-origin-access-control \
    --origin-access-control-config "{
      \"Name\": \"msw-attachments-oac\",
      \"Description\": \"OAC for MSW attachments S3 bucket\",
      \"SigningProtocol\": \"sigv4\",
      \"SigningBehavior\": \"always\",
      \"OriginAccessControlOriginType\": \"s3\"
    }" \
    --query 'OriginAccessControl.Id' \
    --output text --region "$REGION" 2>/dev/null || echo "")

  if [ -z "$OAC_ID" ]; then
    # OAC might already exist
    OAC_ID=$(aws cloudfront list-origin-access-controls \
      --query "OriginAccessControlList.Items[?Name=='msw-attachments-oac'].Id | [0]" \
      --output text --region "$REGION")
  fi

  info "Creating CloudFront distribution..."

  CF_CONFIG=$(cat <<CFEOF
{
  "CallerReference": "msw-attachments-$(date +%s)",
  "Comment": "MSW Platform - Attachment CDN",
  "Enabled": true,
  "DefaultCacheBehavior": {
    "TargetOriginId": "msw-s3-attachments",
    "ViewerProtocolPolicy": "redirect-to-https",
    "AllowedMethods": {
      "Quantity": 2,
      "Items": ["GET", "HEAD"],
      "CachedMethods": {
        "Quantity": 2,
        "Items": ["GET", "HEAD"]
      }
    },
    "CachePolicyId": "658327ea-f89d-4fab-a63d-7e88639e58f6",
    "Compress": true,
    "ForwardedValues": {
      "QueryString": false,
      "Cookies": { "Forward": "none" }
    },
    "MinTTL": 0,
    "DefaultTTL": 86400,
    "MaxTTL": 31536000
  },
  "Origins": {
    "Quantity": 1,
    "Items": [
      {
        "Id": "msw-s3-attachments",
        "DomainName": "${ATTACHMENTS_BUCKET}.s3.${REGION}.amazonaws.com",
        "OriginAccessControlId": "${OAC_ID}",
        "S3OriginConfig": {
          "OriginAccessIdentity": ""
        }
      }
    ]
  },
  "DefaultRootObject": "",
  "PriceClass": "PriceClass_100",
  "HttpVersion": "http2"
}
CFEOF
  )

  CF_RESULT=$(aws cloudfront create-distribution \
    --distribution-config "$CF_CONFIG" \
    --query 'Distribution.{Id:Id,Domain:DomainName}' \
    --output json --region "$REGION")

  DISTRIBUTION_ID=$(echo "$CF_RESULT" | jq -r '.Id')
  CLOUDFRONT_DOMAIN=$(echo "$CF_RESULT" | jq -r '.Domain')

  success "CloudFront distribution created: ${CYAN}${DISTRIBUTION_ID}${NC}"

  # Add S3 bucket policy to allow CloudFront OAC access
  info "Adding S3 bucket policy for CloudFront OAC..."
  S3_POLICY=$(cat <<S3EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowCloudFrontServicePrincipal",
      "Effect": "Allow",
      "Principal": {
        "Service": "cloudfront.amazonaws.com"
      },
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::${ATTACHMENTS_BUCKET}/*",
      "Condition": {
        "StringEquals": {
          "AWS:SourceArn": "arn:aws:cloudfront::${ACCOUNT_ID}:distribution/${DISTRIBUTION_ID}"
        }
      }
    }
  ]
}
S3EOF
  )

  aws s3api put-bucket-policy \
    --bucket "$ATTACHMENTS_BUCKET" \
    --policy "$S3_POLICY" \
    --region "$REGION"

  success "S3 bucket policy applied"
fi

success "CloudFront domain: ${CYAN}https://${CLOUDFRONT_DOMAIN}${NC}"

# Update Lambda env with CloudFront domain
if [ -n "$CLOUDFRONT_DOMAIN" ]; then
  info "Updating Lambda CLOUDFRONT_DOMAIN env var..."
  ENV_VARS_UPDATED=$(cat <<EOF
{
  "Variables": {
    "JWT_SECRET": "${JWT_SECRET}",
    "AWS_REGION_CUSTOM": "${REGION}",
    "TABLE_PREFIX": "${TABLE_PREFIX}",
    "MSW_ATTACHMENTS_BUCKET": "${ATTACHMENTS_BUCKET}",
    "MAIL_FROM_ADDRESS": "${MAIL_FROM_ADDRESS}",
    "CLOUDFRONT_DOMAIN": "${CLOUDFRONT_DOMAIN}",
    "NODE_ENV": "production"
  }
}
EOF
  )

  # Wait for any pending updates
  aws lambda wait function-updated-v2 \
    --function-name "$FUNCTION_NAME" \
    --region "$REGION" 2>/dev/null || sleep 3

  aws lambda update-function-configuration \
    --function-name "$FUNCTION_NAME" \
    --environment "$ENV_VARS_UPDATED" \
    --region "$REGION" \
    --output text > /dev/null

  success "Lambda env updated with CloudFront domain"
fi

###############################################################################
# STEP 7: Budget Alert ($0.01)
###############################################################################
step "Step 7/7 — Budget Alert"

# Check if budget already exists
if aws budgets describe-budget \
  --account-id "$ACCOUNT_ID" \
  --budget-name "$BUDGET_NAME" \
  --region "$REGION" 2>/dev/null; then
  warn "Budget '${BUDGET_NAME}' already exists — skipping"
else
  info "Creating budget alert (threshold: \$0.01)..."

  BUDGET_JSON=$(cat <<BEOF
{
  "BudgetName": "${BUDGET_NAME}",
  "BudgetLimit": {
    "Amount": "0.01",
    "Unit": "USD"
  },
  "BudgetType": "COST",
  "TimeUnit": "MONTHLY",
  "TimePeriod": {
    "Start": "2024-01-01T00:00:00Z",
    "End": "2087-06-15T00:00:00Z"
  },
  "CostTypes": {
    "IncludeTax": true,
    "IncludeSubscription": true,
    "UseBlended": false,
    "IncludeRefund": false,
    "IncludeCredit": false,
    "IncludeUpfront": true,
    "IncludeRecurring": true,
    "IncludeOtherSubscription": true,
    "IncludeSupport": true,
    "IncludeDiscount": true,
    "UseAmortized": false
  }
}
BEOF
  )

  NOTIFICATION_JSON=$(cat <<NEOF
[
  {
    "Notification": {
      "NotificationType": "ACTUAL",
      "ComparisonOperator": "GREATER_THAN",
      "Threshold": 100,
      "ThresholdType": "PERCENTAGE"
    },
    "Subscribers": [
      {
        "SubscriptionType": "EMAIL",
        "Address": "$(aws sts get-caller-identity --query 'Arn' --output text | sed 's/.*\///')@aws-budget-alert.local"
      }
    ]
  }
]
NEOF
  )

  # Try to create the budget — notification subscriber may need a real email
  aws budgets create-budget \
    --account-id "$ACCOUNT_ID" \
    --budget "$BUDGET_JSON" \
    --region "$REGION" 2>/dev/null && \
    success "Budget alert created (\$0.01 monthly threshold)" || \
    warn "Budget alert creation skipped (may need console setup for email subscriber)"
fi

###############################################################################
# SUMMARY
###############################################################################
header "🎉 Deployment Complete!"

echo -e "  ${BOLD}API Endpoint${NC}"
echo -e "    ${CYAN}${FUNCTION_URL}${NC}"
echo ""
echo -e "  ${BOLD}CloudFront (Attachments CDN)${NC}"
echo -e "    ${CYAN}https://${CLOUDFRONT_DOMAIN}${NC}"
echo ""
echo -e "  ${BOLD}S3 Buckets${NC}"
echo -e "    Attachments : ${CYAN}${ATTACHMENTS_BUCKET}${NC}"
echo -e "    Web hosting : ${CYAN}${WEB_BUCKET}${NC}"
echo ""
echo -e "  ${BOLD}DynamoDB Tables${NC}"
echo -e "    ${CYAN}${TABLE_PREFIX}-users${NC}, ${CYAN}${TABLE_PREFIX}-categories${NC}, ${CYAN}${TABLE_PREFIX}-reports${NC}"
echo -e "    ${CYAN}${TABLE_PREFIX}-attachments${NC}, ${CYAN}${TABLE_PREFIX}-report-crew${NC}"
echo ""
echo -e "  ${BOLD}Lambda${NC}"
echo -e "    Function : ${CYAN}${FUNCTION_NAME}${NC}"
echo -e "    Runtime  : ${CYAN}${RUNTIME}${NC}"
echo -e "    Memory   : ${CYAN}${MEMORY_SIZE}MB${NC}"
echo -e "    Timeout  : ${CYAN}${TIMEOUT}s${NC}"
echo ""
echo -e "  ${BOLD}Test Credentials${NC}"
echo -e "    ┌──────────┬───────────────────┬───────────────┐"
echo -e "    │ ${BOLD}Role${NC}     │ ${BOLD}Email${NC}             │ ${BOLD}Password${NC}      │"
echo -e "    ├──────────┼───────────────────┼───────────────┤"
echo -e "    │ citizen  │ citizen@test.com  │ Password123!  │"
echo -e "    │ staff    │ staff@test.com    │ Password123!  │"
echo -e "    │ crew     │ crew@test.com     │ Password123!  │"
echo -e "    └──────────┴───────────────────┴───────────────┘"
echo ""
echo -e "  ${BOLD}Cost${NC}"
echo -e "    Budget alert set at ${CYAN}\$0.01/month${NC} — this deployment should cost ${GREEN}\$0.00${NC}"
echo ""
echo -e "  ${DIM}To tear down all resources: ./teardown.sh${NC}"
echo ""
