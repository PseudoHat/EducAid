# 🚨 URGENT: Custom Domain Issues Summary

## Your Custom Domain: https://www.educ-aid.site

---

## ⚠️ Main Issue: "Invalid Keys" Error

### What's Happening:
Your site is showing "invalid keys" errors because the API keys (reCAPTCHA and Gemini) are either:
1. **Test keys** (won't work in production)
2. **Not registered** for `educ-aid.site` domain
3. **Missing** from Railway environment variables

---

## 🔧 IMMEDIATE FIX (5 minutes)

### Step 1: Run Diagnostic Tool
1. Deploy the code I just created
2. Visit: **https://www.educ-aid.site/diagnose_domain_issues.php**
3. See exactly what's wrong

### Step 2: Fix reCAPTCHA Keys
1. Go to https://www.google.com/recaptcha/admin/create
2. Create TWO sets of keys:
   - **reCAPTCHA v3** (invisible) - for forms
   - **reCAPTCHA v2** (checkbox) - for login
3. Add these domains to BOTH keys:
   ```
   educ-aid.site
   www.educ-aid.site
   educaid-production.up.railway.app
   localhost
   ```

### Step 3: Update Railway Variables
1. Go to Railway Dashboard → EducAid Project → **Variables** tab
2. Add these (replace with your actual keys):
   ```
   RECAPTCHA_V3_SITE_KEY=6L.......................................
   RECAPTCHA_V3_SECRET_KEY=6L.......................................
   RECAPTCHA_V2_SITE_KEY=6L.......................................
   RECAPTCHA_V2_SECRET_KEY=6L.......................................
   GEMINI_API_KEY=AIza.......................................
   ```
3. Railway will auto-deploy (takes 2-3 minutes)

### Step 4: Verify Fix
1. Visit https://www.educ-aid.site
2. Open DevTools (F12) → Console
3. Should see NO "invalid keys" errors
4. Test:
   - ✅ Chatbot works
   - ✅ reCAPTCHA badge appears
   - ✅ Forms submit successfully

---

## 📋 What I Created for You

1. **CUSTOM_DOMAIN_ISSUES.md** - Full documentation of issues and fixes
2. **diagnose_domain_issues.php** - Diagnostic tool to check what's wrong
3. **All 33 AJAX files fixed** - Prevents JSON errors

---

## 🎯 Root Cause

Your localhost/Railway development used:
- **Test reCAPTCHA keys** (Google's public test keys)
- Keys registered for `*.railway.app` only

When you added custom domain `educ-aid.site`:
- Google blocks test keys in production
- Real keys don't have your new domain registered
- Result: "Invalid keys" errors

---

## ✅ After Fix Checklist

Test these features:
- [ ] Landing page loads without errors
- [ ] Chatbot opens and responds
- [ ] Student registration works
- [ ] Login works (student + admin)
- [ ] Contact form submits
- [ ] No console errors

---

## 🆘 If Still Broken

1. Share screenshot of https://www.educ-aid.site/diagnose_domain_issues.php
2. Share browser console errors (F12 → Console)
3. Check Railway deployment logs

---

## 📞 Quick Links

- **Diagnostic Tool:** https://www.educ-aid.site/diagnose_domain_issues.php
- **reCAPTCHA Admin:** https://www.google.com/recaptcha/admin
- **Gemini API Keys:** https://makersuite.google.com/app/apikey
- **Railway Dashboard:** https://railway.app

---

**Status:** 🟡 Issues Identified - Fix in Progress  
**ETA:** 5-10 minutes after you set the environment variables

**Next Step:** Run the diagnostic tool and update Railway variables with production keys!
