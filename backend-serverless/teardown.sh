#!/usr/bin/env bash
set -euo pipefail

###############################################################################
#  MSW Platform — Teardown Script
#
#  Removes ALL AWS resources created by deploy.sh:
#    1. Lambda Function URL + Function
#    2. IAM Role + Policies
#    3. S3 Buckets (emptied first)
#    4. DynamoDB Tables
#    5. CloudFront Distribution
#    6. Budget Alert
#
#  Usage: ./teardown.sh
#
#  ⚠️  This is DESTRUCTIVE and cannot be undone!
###############################################################################

# ── Colours ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m'

info()    { echo -e "${CYAN}  ℹ  ${NC}$1"; }
success() { echo -e "${GREEN}  ✅  ${NC}$1"; }
warn()    { echo -e "${YELLOW}  ⏩  ${NC}$1"; }
error()   { echo -e "${RED}  ❌  ${NC}$1"; }
header()  { echo -e "\n${BOLD}═══════════════════════════════════════════════${NC}"; echo -e "${BOLD}  $1${NC}"; echo -e "${BOLD}═══════════════════════════════════════════════${NC}\n"; }
step()    { echo -e "\n${BOLD}── $1 ──${NC}\n"; }

# ── Variables ────────────────────────────────────────────────────────────────
REGION="${AWS_REGION:-ap-southeast-1}"
TABLE_PREFIX="${TABLE_PREFIX:-msw}"
FUNCTION_NAME="msw-api"
ROLE_NAME="msw-lambda-role"
POLICY_NAME="msw-lambda-policy"
BUDGET_NAME="msw-zero-cost-budget"

ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text --region "$REGION")
ATTACHMENTS_BUCKET="${MSW_ATTACHMENTS_BUCKET:-msw-attachments-${ACCOUNT_ID}}"
WEB_BUCKET="${MSW_WEB_BUCKET:-msw-web-${ACCOUNT_ID}}"

header "⚠️  MSW Platform — Teardown"

echo -e "  ${RED}${BOLD}This will PERMANENTLY DELETE all MSW resources!${NC}"
echo ""
echo -e "  Region     : ${CYAN}${REGION}${NC}"
echo -e "  Account    : ${CYAN}${ACCOUNT_ID}${NC}"
echo -e "  Function   : ${CYAN}${FUNCTION_NAME}${NC}"
echo -e "  Buckets    : ${CYAN}${ATTACHMENTS_BUCKET}${NC}, ${CYAN}${WEB_BUCKET}${NC}"
echo -e "  Tables     : ${CYAN}${TABLE_PREFIX}-*${NC}"
echo ""

# Confirm
read -p "  Type 'yes' to proceed: " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
  echo -e "\n  ${YELLOW}Aborted.${NC}\n"
  exit 0
fi

###############################################################################
# STEP 1: Lambda Function URL + Function
###############################################################################
step "Step 1/6 — Lambda Function"

# Delete Function URL first
if aws lambda get-function-url-config --function-name "$FUNCTION_NAME" --region "$REGION" 2>/dev/null; then
  info "Deleting Function URL..."
  aws lambda delete-function-url-config \
    --function-name "$FUNCTION_NAME" \
    --region "$REGION"
  success "Function URL deleted"
else
  warn "No Function URL found — skipping"
fi

# Delete the Lambda function
if aws lambda get-function --function-name "$FUNCTION_NAME" --region "$REGION" 2>/dev/null; then
  info "Deleting Lambda function '${FUNCTION_NAME}'..."
  aws lambda delete-function \
    --function-name "$FUNCTION_NAME" \
    --region "$REGION"
  success "Lambda function deleted"
else
  warn "Lambda function '${FUNCTION_NAME}' not found — skipping"
fi

###############################################################################
# STEP 2: IAM Role + Policies
###############################################################################
step "Step 2/6 — IAM Role"

if aws iam get-role --role-name "$ROLE_NAME" 2>/dev/null; then
  # Delete inline policies
  info "Listing inline policies for '${ROLE_NAME}'..."
  POLICIES=$(aws iam list-role-policies --role-name "$ROLE_NAME" --query 'PolicyNames[]' --output text)
  for POLICY in $POLICIES; do
    info "Deleting inline policy '${POLICY}'..."
    aws iam delete-role-policy --role-name "$ROLE_NAME" --policy-name "$POLICY"
    success "Policy '${POLICY}' deleted"
  done

  # Detach managed policies
  ATTACHED=$(aws iam list-attached-role-policies --role-name "$ROLE_NAME" --query 'AttachedPolicies[].PolicyArn' --output text)
  for ARN in $ATTACHED; do
    info "Detaching managed policy '${ARN}'..."
    aws iam detach-role-policy --role-name "$ROLE_NAME" --policy-arn "$ARN"
    success "Policy detached"
  done

  # Delete the role
  info "Deleting IAM role '${ROLE_NAME}'..."
  aws iam delete-role --role-name "$ROLE_NAME"
  success "IAM role '${ROLE_NAME}' deleted"
else
  warn "IAM role '${ROLE_NAME}' not found — skipping"
fi

###############################################################################
# STEP 3: S3 Buckets
###############################################################################
step "Step 3/6 — S3 Buckets"

delete_s3_bucket() {
  local BUCKET=$1

  if aws s3api head-bucket --bucket "$BUCKET" --region "$REGION" 2>/dev/null; then
    info "Emptying bucket '${BUCKET}'..."
    aws s3 rm "s3://${BUCKET}" --recursive --region "$REGION" --quiet 2>/dev/null || true

    # Also delete any versioned objects
    info "Removing versioned objects..."
    local VERSIONS
    VERSIONS=$(aws s3api list-object-versions \
      --bucket "$BUCKET" \
      --query '{Objects: Versions[].{Key:Key,VersionId:VersionId}}' \
      --output json --region "$REGION" 2>/dev/null || echo '{"Objects":null}')
    
    if [ "$(echo "$VERSIONS" | jq -r '.Objects')" != "null" ]; then
      aws s3api delete-objects \
        --bucket "$BUCKET" \
        --delete "$VERSIONS" \
        --region "$REGION" --quiet 2>/dev/null || true
    fi

    # Delete markers
    local MARKERS
    MARKERS=$(aws s3api list-object-versions \
      --bucket "$BUCKET" \
      --query '{Objects: DeleteMarkers[].{Key:Key,VersionId:VersionId}}' \
      --output json --region "$REGION" 2>/dev/null || echo '{"Objects":null}')

    if [ "$(echo "$MARKERS" | jq -r '.Objects')" != "null" ]; then
      aws s3api delete-objects \
        --bucket "$BUCKET" \
        --delete "$MARKERS" \
        --region "$REGION" --quiet 2>/dev/null || true
    fi

    info "Deleting bucket '${BUCKET}'..."
    aws s3api delete-bucket --bucket "$BUCKET" --region "$REGION"
    success "Bucket '${BUCKET}' deleted"
  else
    warn "Bucket '${BUCKET}' not found — skipping"
  fi
}

delete_s3_bucket "$ATTACHMENTS_BUCKET"
delete_s3_bucket "$WEB_BUCKET"

###############################################################################
# STEP 4: DynamoDB Tables
###############################################################################
step "Step 4/6 — DynamoDB Tables"

DYNAMO_TABLES=(
  "${TABLE_PREFIX}-users"
  "${TABLE_PREFIX}-categories"
  "${TABLE_PREFIX}-reports"
  "${TABLE_PREFIX}-attachments"
  "${TABLE_PREFIX}-report-crew"
)

for TABLE in "${DYNAMO_TABLES[@]}"; do
  if aws dynamodb describe-table --table-name "$TABLE" --region "$REGION" 2>/dev/null; then
    info "Deleting table '${TABLE}'..."
    aws dynamodb delete-table --table-name "$TABLE" --region "$REGION" --output text > /dev/null
    success "Table '${TABLE}' deleted"
  else
    warn "Table '${TABLE}' not found — skipping"
  fi
done

# Wait for tables to be deleted
info "Waiting for tables to finish deleting..."
for TABLE in "${DYNAMO_TABLES[@]}"; do
  aws dynamodb wait table-not-exists --table-name "$TABLE" --region "$REGION" 2>/dev/null || true
done
success "All DynamoDB tables deleted"

###############################################################################
# STEP 5: CloudFront Distribution
###############################################################################
step "Step 5/6 — CloudFront Distribution"

# Find distributions for our bucket
DIST_IDS=$(aws cloudfront list-distributions \
  --query "DistributionList.Items[?Origins.Items[0].DomainName=='${ATTACHMENTS_BUCKET}.s3.${REGION}.amazonaws.com'].Id" \
  --output text --region "$REGION" 2>/dev/null || echo "")

if [ -n "$DIST_IDS" ] && [ "$DIST_IDS" != "None" ]; then
  for DIST_ID in $DIST_IDS; do
    info "Disabling CloudFront distribution '${DIST_ID}'..."

    # Get current config
    ETAG=$(aws cloudfront get-distribution-config \
      --id "$DIST_ID" \
      --query 'ETag' \
      --output text --region "$REGION")

    DIST_CONFIG=$(aws cloudfront get-distribution-config \
      --id "$DIST_ID" \
      --query 'DistributionConfig' \
      --output json --region "$REGION")

    # Disable it
    DISABLED_CONFIG=$(echo "$DIST_CONFIG" | jq '.Enabled = false')

    aws cloudfront update-distribution \
      --id "$DIST_ID" \
      --distribution-config "$DISABLED_CONFIG" \
      --if-match "$ETAG" \
      --region "$REGION" --output text > /dev/null

    info "Waiting for distribution to be disabled (this can take several minutes)..."
    aws cloudfront wait distribution-deployed \
      --id "$DIST_ID" \
      --region "$REGION" 2>/dev/null || sleep 30

    # Get new ETag after update
    NEW_ETAG=$(aws cloudfront get-distribution-config \
      --id "$DIST_ID" \
      --query 'ETag' \
      --output text --region "$REGION")

    info "Deleting distribution '${DIST_ID}'..."
    aws cloudfront delete-distribution \
      --id "$DIST_ID" \
      --if-match "$NEW_ETAG" \
      --region "$REGION"
    success "CloudFront distribution '${DIST_ID}' deleted"
  done

  # Clean up OAC
  OAC_ID=$(aws cloudfront list-origin-access-controls \
    --query "OriginAccessControlList.Items[?Name=='msw-attachments-oac'].Id | [0]" \
    --output text --region "$REGION" 2>/dev/null || echo "")

  if [ -n "$OAC_ID" ] && [ "$OAC_ID" != "None" ]; then
    OAC_ETAG=$(aws cloudfront get-origin-access-control \
      --id "$OAC_ID" \
      --query 'ETag' \
      --output text --region "$REGION")
    
    aws cloudfront delete-origin-access-control \
      --id "$OAC_ID" \
      --if-match "$OAC_ETAG" \
      --region "$REGION"
    success "Origin Access Control deleted"
  fi
else
  warn "No CloudFront distributions found for attachments bucket — skipping"
fi

###############################################################################
# STEP 6: Budget Alert
###############################################################################
step "Step 6/6 — Budget Alert"

if aws budgets describe-budget \
  --account-id "$ACCOUNT_ID" \
  --budget-name "$BUDGET_NAME" \
  --region "$REGION" 2>/dev/null; then
  info "Deleting budget '${BUDGET_NAME}'..."
  aws budgets delete-budget \
    --account-id "$ACCOUNT_ID" \
    --budget-name "$BUDGET_NAME" \
    --region "$REGION"
  success "Budget '${BUDGET_NAME}' deleted"
else
  warn "Budget '${BUDGET_NAME}' not found — skipping"
fi

###############################################################################
# DONE
###############################################################################
header "🧹 Teardown Complete!"

echo -e "  All MSW Platform resources have been ${GREEN}removed${NC}."
echo ""
echo -e "  Deleted:"
echo -e "    • Lambda function:  ${DIM}${FUNCTION_NAME}${NC}"
echo -e "    • IAM role:         ${DIM}${ROLE_NAME}${NC}"
echo -e "    • S3 buckets:       ${DIM}${ATTACHMENTS_BUCKET}, ${WEB_BUCKET}${NC}"
echo -e "    • DynamoDB tables:  ${DIM}${TABLE_PREFIX}-*${NC}"
echo -e "    • CloudFront dist:  ${DIM}(if existed)${NC}"
echo -e "    • Budget alert:     ${DIM}${BUDGET_NAME}${NC}"
echo ""
echo -e "  ${DIM}To redeploy: ./deploy.sh${NC}"
echo ""
