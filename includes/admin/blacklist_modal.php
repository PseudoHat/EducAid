<!-- Blacklist Modal -->
<div class="modal fade" id="blacklistModal" tabindex="-1" aria-labelledby="blacklistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="blacklistModalLabel">
                    <i class="bi bi-shield-exclamation"></i> Blacklist Student
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="blacklistForm">
                    <input type="hidden" id="blacklist_student_id" name="student_id">
                    <input type="hidden" name="csrf_token" value="<?php echo CSRFProtection::generateToken('blacklist_operation'); ?>">
                    
                    <p class="mb-3">
                        You are about to blacklist: <strong id="studentInfo"></strong>
                    </p>

                    <div class="mb-3">
                        <label for="reason_category" class="form-label">
                            Reason for Blacklisting <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="reason_category" name="reason_category" required>
                            <option value="">-- Select Reason --</option>
                            <option value="fraudulent_activity">Fraudulent Activity</option>
                            <option value="academic_misconduct">Academic Misconduct</option>
                            <option value="system_abuse">System Abuse</option>
                            <option value="duplicate">Duplicate Account</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="detailed_reason" class="form-label">
                            Details <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="detailed_reason" name="detailed_reason" 
                                  rows="3" placeholder="Provide details about why this student is being blacklisted..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="admin_password" class="form-label">
                            Your Admin Password <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control" id="admin_password" 
                               name="admin_password" placeholder="Enter your password to confirm" required>
                    </div>

                    <div id="otpSection" class="mb-3" style="display: none;">
                        <label for="blacklist_otp" class="form-label">
                            Verification Code <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="blacklist_otp" name="otp" 
                               placeholder="Enter 6-digit code from email" maxlength="6">
                        <small class="form-text text-muted">Check your email for the verification code.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="sendOtpBtn" onclick="initiateBlacklist(); return false;">Send Code</button>
                <button type="button" class="btn btn-danger" id="confirmBlacklistBtn" 
                        onclick="completeBlacklist(); return false;" style="display: none;">Confirm Blacklist</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentBlacklistStudent = null;

function showBlacklistModal(studentId, studentName, studentEmail, additionalInfo = {}) {
    currentBlacklistStudent = {
        id: studentId,
        name: studentName,
        email: studentEmail,
        ...additionalInfo
    };
    
    // Reset form
    document.getElementById('blacklistForm').reset();
    document.getElementById('blacklist_student_id').value = studentId;
    document.getElementById('otpSection').style.display = 'none';
    document.getElementById('sendOtpBtn').style.display = 'inline-block';
    document.getElementById('confirmBlacklistBtn').style.display = 'none';
    
    // Populate student info
    let studentInfoHtml = `
        <div class="row">
            <div class="col-md-6">
                <strong>Name:</strong> ${studentName}<br>
                <strong>Email:</strong> ${studentEmail}
            </div>
            <div class="col-md-6">
    `;
    
    if (additionalInfo.barangay) {
        studentInfoHtml += `<strong>Barangay:</strong> ${additionalInfo.barangay}<br>`;
    }
    if (additionalInfo.university) {
        studentInfoHtml += `<strong>University:</strong> ${additionalInfo.university}<br>`;
    }
    if (additionalInfo.status) {
        studentInfoHtml += `<strong>Status:</strong> <span class="badge bg-info">${additionalInfo.status}</span>`;
    }
    
    studentInfoHtml += `
            </div>
        </div>
    `;
    
    document.getElementById('studentInfo').innerHTML = studentInfoHtml;
    
    // Show modal
    new bootstrap.Modal(document.getElementById('blacklistModal')).show();
    
    // Prevent form submission
    const form = document.getElementById('blacklistForm');
    form.onsubmit = function(e) {
        e.preventDefault();
        return false;
    };
}

function initiateBlacklist() {
    const formData = new FormData(document.getElementById('blacklistForm'));
    formData.append('action', 'initiate_blacklist');
    
    console.log('Initiating blacklist...'); // Debug log
    
    // Validate required fields
    if (!formData.get('reason_category')) {
        alert('Please select a reason for blacklisting.');
        return;
    }
    
    if (!formData.get('admin_password')) {
        alert('Please enter your admin password.');
        return;
    }
    
    // Show loading state
    const sendBtn = document.getElementById('sendOtpBtn');
    const originalText = sendBtn.innerHTML;
    sendBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending...';
    sendBtn.disabled = true;
    
    fetch('blacklist_service.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status); // Debug log
        console.log('Response headers:', response.headers); // Debug log
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.text().then(text => {
            console.log('Raw response:', text); // Debug log
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error('Invalid JSON response: ' + text);
            }
        });
    })
    .then(data => {
        console.log('Parsed data:', data); // Debug log
        
        if (data.status === 'otp_sent') {
            // Show OTP section
            document.getElementById('otpSection').style.display = 'block';
            document.getElementById('sendOtpBtn').style.display = 'none';
            document.getElementById('confirmBlacklistBtn').style.display = 'inline-block';
            
            // Show success message
            showAlert('success', data.message);
            
            // Focus on OTP input
            setTimeout(() => {
                document.getElementById('blacklist_otp').focus();
            }, 500);
            
        } else {
            showAlert('danger', data.message || 'Unknown error occurred');
            sendBtn.innerHTML = originalText;
            sendBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error); // Debug log
        showAlert('danger', 'Network error: ' + error.message);
        sendBtn.innerHTML = originalText;
        sendBtn.disabled = false;
    });
}

function completeBlacklist() {
    const otp = document.getElementById('blacklist_otp').value;
    
    if (!otp || otp.length !== 6) {
        alert('Please enter the 6-digit verification code.');
        return;
    }
    
    if (!confirm(`FINAL CONFIRMATION: This will permanently blacklist ${currentBlacklistStudent.name}. This action cannot be undone. Continue?`)) {
        return;
    }
    
    // Get CSRF token from the form
    const csrfToken = document.querySelector('#blacklistForm input[name="csrf_token"]').value;
    
    const formData = new FormData();
    formData.append('action', 'complete_blacklist');
    formData.append('student_id', currentBlacklistStudent.id);
    formData.append('otp', otp);
    formData.append('csrf_token', csrfToken);
    
    console.log('Completing blacklist for student:', currentBlacklistStudent.id);
    
    // Show loading state
    const confirmBtn = document.getElementById('confirmBlacklistBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
    confirmBtn.disabled = true;
    
    fetch('blacklist_service.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status); // Debug log
        console.log('Response headers:', response.headers); // Debug log
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.text().then(text => {
            console.log('Raw response:', text); // Debug log
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response: ' + text);
            }
        });
    })
    .then(data => {
        console.log('Parsed data:', data); // Debug log
        
        if (data.status === 'success') {
            showAlert('success', data.message);
            
            // Close modal and refresh page
            bootstrap.Modal.getInstance(document.getElementById('blacklistModal')).hide();
            setTimeout(() => {
                location.reload();
            }, 2000);
            
        } else {
            showAlert('danger', data.message || 'Unknown error occurred');
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error); // Debug log
        showAlert('danger', 'Network error: ' + error.message);
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    });
}

function showAlert(type, message) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at top of modal body
    const modalBody = document.querySelector('#blacklistModal .modal-body');
    modalBody.insertBefore(alertDiv, modalBody.firstChild);
    
    // Auto-hide success alerts
    if (type === 'success') {
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}
</script>

<style>
#blacklistModal .modal-content {
    border: 3px solid #dc3545;
}

#blacklistModal .alert-danger {
    border-left: 5px solid #dc3545;
}

#blacklistModal .form-label.fw-bold {
    color: #495057;
}

#blacklistModal .form-label.fw-bold.text-danger {
    color: #dc3545 !important;
}

#blacklistModal .btn-danger {
    background: linear-gradient(45deg, #dc3545, #c82333);
    border: none;
    font-weight: bold;
}

#blacklistModal .btn-danger:hover {
    background: linear-gradient(45deg, #c82333, #bd2130);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}
</style>