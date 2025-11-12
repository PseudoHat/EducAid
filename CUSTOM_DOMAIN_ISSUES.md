# 🐛 Custom Domain Issues - www.educ-aid.site

## Date: November 13, 2025

---

## 🔍 Observed Issues

### 1. **Invalid Keys Error**
**Symptoms:**
- Console shows "invalid keys" errors
- Features not working properly
- API integration failures

**Root Causes:**
1. **reCAPTCHA Keys** - Using test keys instead of production keys
2. **Gemini API Key** - May not be set in Railway environment variables
3. **Domain Mismatch** - Keys registered for railway.app domain, not educ-aid.site

---

## 🔧 Required Fixes

### Fix 1: Update reCAPTCHA Keys for Custom Domain

#### Problem:
Current keys are either test keys or registered for `*.railway.app` domain. They won't work on `educ-aid.site`.

#### Solution:
1. Go to [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
2. Create NEW keys or update existing ones
3. Add these domains:
   - `educ-aid.site`
   - `www.educ-aid.site`
   - `educaid-production.up.railway.app` (keep as backup)
   - `localhost` (for development)

#### Railway Environment Variables to Set:
```bash
# reCAPTCHA v3 (invisible)
RECAPTCHA_V3_SITE_KEY=your_new_v3_site_key_here
RECAPTCHA_V3_SECRET_KEY=your_new_v3_secret_key_here

# reCAPTCHA v2 (checkbox)
RECAPTCHA_V2_SITE_KEY=your_new_v2_site_key_here
RECAPTCHA_V2_SECRET_KEY=your_new_v2_secret_key_here
```

---

### Fix 2: Verify Gemini API Key

#### Problem:
Chatbot may fail if `GEMINI_API_KEY` is not set in Railway

#### Solution:
1. Go to [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Create API key
3. Add to Railway environment variables:
```bash
GEMINI_API_KEY=your_gemini_api_key_here
```

---

### Fix 3: Update Security Headers for Custom Domain

#### File: `config/security_headers.php`

**Current Issue:** CSP (Content Security Policy) might block resources on new domain

#### Check if these are set correctly:
```php
"frame-ancestors 'self' https://educ-aid.site https://www.educ-aid.site",
"form-action 'self' https://educ-aid.site https://www.educ-aid.site",
```

---

## 📋 Step-by-Step Fix Guide

### Step 1: Update reCAPTCHA Keys
1. Open Railway Dashboard → EducAid project
2. Go to **Variables** tab
3. Add/Update these variables:
   ```
   RECAPTCHA_V3_SITE_KEY=6L...
   RECAPTCHA_V3_SECRET_KEY=6L...
   RECAPTCHA_V2_SITE_KEY=6L...
   RECAPTCHA_V2_SECRET_KEY=6L...
   ```
4. Click **Deploy** to apply changes

### Step 2: Verify Gemini API Key
1. Check if `GEMINI_API_KEY` exists in Railway variables
2. If not, add it:
   ```
   GEMINI_API_KEY=AIza...
   ```
3. Click **Deploy**

### Step 3: Test After Deployment
1. Visit https://www.educ-aid.site
2. Open Browser DevTools (F12) → Console
3. Check for errors:
   - ✅ No "invalid keys" errors
   - ✅ reCAPTCHA loads correctly
   - ✅ Chatbot works
   - ✅ Forms submit successfully

---

## 🔍 How to Check Current Keys

### Check reCAPTCHA Key Registration:
1. View page source on https://www.educ-aid.site
2. Search for `recaptcha/api.js`
3. Find the site key in the URL
4. Go to [reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
5. Find that key and check which domains are registered

### Check Gemini API:
1. SSH into Railway or check logs
2. Look for errors like:
   ```
   Missing GEMINI_API_KEY in environment
   API key not valid
   ```

---

## ⚠️ Common Errors and Solutions

### Error: "ERROR for site owner: Invalid key"
**Cause:** reCAPTCHA key not registered for `educ-aid.site`  
**Fix:** Add domain to reCAPTCHA key settings

### Error: "Missing GEMINI_API_KEY"
**Cause:** Environment variable not set  
**Fix:** Add `GEMINI_API_KEY` to Railway variables

### Error: "This site key is not enabled for the invisible captcha"
**Cause:** Using v2 key for v3 implementation  
**Fix:** Use correct key type (v3 for invisible, v2 for checkbox)

### Error: Chatbot not responding
**Cause:** Invalid or missing Gemini API key  
**Fix:** Verify API key is valid and has quota

---

## 🧪 Testing Checklist

After fixing, test these features:

### Public Website:
- [ ] Landing page loads without console errors
- [ ] reCAPTCHA badge appears (bottom-right)
- [ ] Chatbot opens and responds
- [ ] Contact form works
- [ ] Registration form works
- [ ] No "invalid keys" in console

### Student Portal:
- [ ] Login works
- [ ] Registration works
- [ ] Document upload works
- [ ] No API errors

### Admin Panel:
- [ ] Login works
- [ ] CMS editors work
- [ ] No JSON parse errors
- [ ] Save functions work

---

## 📊 Railway Environment Variables Checklist

Required variables for production:
```bash
✅ DATABASE_URL=postgresql://...
✅ RECAPTCHA_V3_SITE_KEY=...
✅ RECAPTCHA_V3_SECRET_KEY=...
✅ RECAPTCHA_V2_SITE_KEY=...
✅ RECAPTCHA_V2_SECRET_KEY=...
✅ GEMINI_API_KEY=...
✅ SESSION_SECRET=... (random string)
```

Optional but recommended:
```bash
□ SMTP_HOST=...
□ SMTP_PORT=...
□ SMTP_USER=...
□ SMTP_PASS=...
```

---

## 🚀 Quick Fix Commands

### Deploy after adding variables:
```bash
# Variables are auto-deployed, but you can force redeploy:
railway up
```

### Check Railway logs:
```bash
railway logs
```

### Test API endpoints:
```bash
# Test Gemini chatbot
curl https://www.educ-aid.site/chatbot/gemini_chat_fast.php \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"message":"Hello"}'

# Should NOT return: "Missing GEMINI_API_KEY"
```

---

## 📝 Summary

**Primary Issue:** API keys (reCAPTCHA, Gemini) not configured for custom domain

**Solution:**
1. Update reCAPTCHA keys to include `educ-aid.site` domain
2. Verify Gemini API key is set in Railway
3. Redeploy Railway project
4. Test all features

**ETA:** 10-15 minutes to fix, 2-3 minutes for Railway to deploy

---

## 🆘 Need Help?

If errors persist after fixing:
1. Share screenshot of browser console errors
2. Share Railway deployment logs
3. Verify all environment variables are set correctly

**Status:** 🔴 Issues Identified - Awaiting Fix
