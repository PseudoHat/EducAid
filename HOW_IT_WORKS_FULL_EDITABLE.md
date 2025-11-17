# How It Works Page - Full CMS Editable Content

## Summary
All text content on the "How It Works" page has been made editable through the CMS system. Previously, only some hero and overview sections were editable, but now **every text element** can be customized.

## Changes Made (November 17, 2025)

### Newly Editable Sections

#### 1. **Step 1: Registration & Account Verification**
- `hiw_step1_detail_title` - Section heading
- `hiw_step1_detail_subtitle` - Section subtitle
- `hiw_step1_needs_title` - "What you'll need:" heading
- `hiw_step1_need1` through `hiw_step1_need4` - Requirements list items
- `hiw_step1_process_title` - "The process:" heading
- `hiw_step1_sub1_title` through `hiw_step1_sub4_title` - Process step titles
- `hiw_step1_sub1_desc` through `hiw_step1_sub4_desc` - Process step descriptions
- `hiw_step1_security_note` - Security alert message

#### 2. **Step 2: Complete Application & Upload Documents**
- `hiw_step2_detail_title` - Section heading
- `hiw_step2_detail_subtitle` - Section subtitle
- `hiw_step2_sections_title` - "Application Form Sections:" heading
- `hiw_step2_accord1_title` through `hiw_step2_accord3_title` - Accordion titles
- `hiw_step2_accord1_desc` through `hiw_step2_accord3_desc` - Accordion descriptions
- `hiw_step2_important_note` - Important alert message

#### 3. **Step 3: Evaluation & Approval Process**
- `hiw_step3_detail_title` - Section heading
- `hiw_step3_detail_subtitle` - Section subtitle
- `hiw_step3_during_title` - "What happens during evaluation:" heading
- `hiw_step3_timeline1_title` through `hiw_step3_timeline3_title` - Timeline step titles
- `hiw_step3_timeline1_desc` through `hiw_step3_timeline3_desc` - Timeline descriptions
- `hiw_step3_status_updates` - Status updates alert
- `hiw_step3_status_title` - Application status heading
- `hiw_step3_status1` through `hiw_step3_status4` - Status labels
- `hiw_step3_status1_label` through `hiw_step3_status4_label` - Status sub-labels

#### 4. **Step 4: QR Code Generation & Claiming**
- `hiw_step4_detail_title` - Section heading
- `hiw_step4_detail_subtitle` - Section subtitle
- `hiw_step4_after_title` - "After approval:" heading
- `hiw_step4_card1_title` through `hiw_step4_card3_title` - Card headings
- `hiw_step4_card1_desc` through `hiw_step4_card2_desc` - Card descriptions
- `hiw_step4_bring1` through `hiw_step4_bring3` - "What to Bring" list items
- `hiw_step4_sample_title` - Sample QR code heading
- `hiw_step4_sample_desc` - Sample description
- `hiw_step4_secure_note` - Security alert message

#### 5. **Tips for Success Section**
- `hiw_tips_title` - Section title
- `hiw_tips_lead` - Section lead text
- `hiw_tips_card1_title` through `hiw_tips_card3_title` - Card headings
- `hiw_tips_card1_item1` through `hiw_tips_card1_item4` - Document Quality tips
- `hiw_tips_card2_item1` through `hiw_tips_card2_item4` - Timing tips
- `hiw_tips_card3_item1` through `hiw_tips_card3_item4` - Security tips

#### 6. **Call-to-Action Section**
- `hiw_cta_title` - CTA heading
- `hiw_cta_lead` - CTA description
- `hiw_cta_btn1` - First button text with icon
- `hiw_cta_btn2` - Second button text with icon

### Already Editable (from before)
- Hero section (title and lead)
- Overview section (title, lead, and 4 step cards)

## Total Editable Blocks
**85+ individual content blocks** are now editable on the How It Works page!

## Database Structure
All content is stored in the `how_it_works_content_blocks` table with the following structure:
```sql
CREATE TABLE how_it_works_content_blocks (
    id SERIAL PRIMARY KEY,
    municipality_id INT NOT NULL DEFAULT 1,
    block_key TEXT NOT NULL,
    html TEXT NOT NULL,
    text_color VARCHAR(20) DEFAULT NULL,
    bg_color VARCHAR(20) DEFAULT NULL,
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(municipality_id, block_key)
);
```

## How to Edit
1. Navigate to: `https://localhost/EducAid/website/how-it-works.php?edit=1&municipality_id=1`
2. Log in as super admin
3. Click on any text element to edit it inline
4. Use the rich text editor to format content
5. Save changes

## Features
- ✅ Inline editing for all text content
- ✅ HTML support for formatting (bold, italic, lists, icons)
- ✅ Color customization for text and backgrounds
- ✅ Change history tracking with audit log
- ✅ Rollback capability
- ✅ Municipality-specific content
- ✅ Safe HTML sanitization (XSS protection)

## Testing Instructions
1. Access edit mode: `?edit=1&municipality_id=1`
2. Try editing different sections:
   - Hero titles
   - Step-by-step instructions
   - Accordion content
   - Timeline items
   - Status badges
   - Tips lists
   - CTA buttons
3. Verify changes persist after page reload
4. Test on mobile and desktop views

## Notes
- Icons can be edited as part of the HTML content (e.g., `<i class="bi bi-check-circle"></i>`)
- List items maintain their formatting even when edited
- Colors can be customized per block
- Default values are preserved in the PHP code as fallbacks
