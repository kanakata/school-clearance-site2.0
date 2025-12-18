const statuses = Array.from({length: 6}, (_, i) => document.querySelector(`.deptstatus${i+1}`)?.innerText?.trim().toLowerCase() || "");
const percentages = Array.from({length: 6}, (_, i) => document.querySelector(`.percentage${i+1}`));
const animations = Array.from({length: 6}, (_, i) => document.querySelector(`.deptanimation${i+1}`));

const totalpercentage = document.querySelector('.totalpercentage');
const dayEl = document.getElementById('day');
const physicall = document.getElementById('physicall');
const replace = document.getElementById('replace');

// 1. CONFIGURATION: Kenyan National Holidays
// Note: This list should be updated annually. Format: "YYYY-MM-DD"
const KENYAN_HOLIDAYS = [
    "2025-01-01", // New Year's Day
    "2025-04-18", // Good Friday
    "2025-04-21", // Easter Monday (Easter Sunday is the 20th)
    "2025-05-01", // Labour Day
    "2025-06-01", // Madaraka Day
    "2025-10-10", // Moi Day (Observed - subject to change)
    "2025-10-20", // Mashujaa Day
    "2025-12-12", // Jamhuri Day
    "2025-12-25", // Christmas Day
    "2025-12-26", // Boxing Day
    // Add Muslim holidays (Eid al-Fitr, Eid al-Adha) which depend on the moon
];

// 2. CONFIGURATION: Local Storage Key 
// Key used to store the fixed pickup date in the user's browser
const PICKUP_DATE_STORAGE_KEY = 'certificatePickupDate';

// 3. HELPER FUNCTION: Holiday Check ---
function isHoliday(date) {
    const dateStr = date.toISOString().slice(0, 10);
    return KENYAN_HOLIDAYS.includes(dateStr);
}

// --- 4. HELPER FUNCTION: Find Next Assigned Day ---
function findNextAssignedDate(targetDayIndex) {
    let date = new Date();
    // Start by moving to the next day to avoid returning today's date
    date.setDate(date.getDate() + 1);

    // Loop until we find a date that matches the target day and is not a holiday
    while (true) {
        if (date.getDay() === targetDayIndex) {
            if (!isHoliday(date)) {
                return date; // Found a valid date
            }
        }
        // Move to the next day
        date.setDate(date.getDate() + 1);
    }
}

// Update animations and percentage displays based on status
statuses.forEach((status, i) => {
    const anim = animations[i];
    const pctEl = percentages[i];
    
    // --- MODIFICATION START ---
    // Consider 'cleared' OR 'pending_physical' as 100% complete for calculation
    const isComplete = status === 'cleared' || status === 'pending_physical';
    
    if (anim) anim.style.height = isComplete ? '100%' : '0%';
    if (pctEl) pctEl.innerText = isComplete ? '100' : '0'; // Changed '100%' to '100' for math
    // --- MODIFICATION END ---
});

// --- 5. MAIN FUNCTION: Calculate and Assign Date ---
function calculateTotalPercentage() {
    // 5a. Calculate Average Percentage
    const sum = percentages.reduce((acc, el) => {
        if (!el) return acc;
        const n = parseInt(el.innerText) || 0;
        return acc + n;
    }, 0);

    const avg = Math.round(sum / 6) || 0;
    if (totalpercentage) totalpercentage.innerText = avg + '%';

    // 5b. Check for Existing Assigned Date
    let assignedDate = localStorage.getItem(PICKUP_DATE_STORAGE_KEY);

    if (dayEl) {
        if (assignedDate) {
            // If a date is already stored, display it regardless of current status
            dayEl.innerText = `✅ Your leaving certificate pickup date is due on: ${assignedDate}`;
        } else if (avg === 100) {
            // If clearance is complete (all departments are 'cleared' OR 'pending_physical') 
            // AND no date is stored, calculate and store the new date
            const todayIndex = new Date().getDay(); // 0=Sun, 1=Mon, ..., 6=Sat
            let targetDayIndex; // Target Day: 1=Mon, 3=Wed, 5=Fri

            // Determine the next target pickup day based on today's day
            switch (todayIndex) {
                case 0: // Sunday
                case 1: // Monday
                case 2: // Tuesday
                    targetDayIndex = 5; // Target: Friday
                    break;
                case 3: // Wednesday
                case 4: // Thursday
                    targetDayIndex = 1; // Target: Monday
                    break;
                case 5: // Friday
                    targetDayIndex = 3; // Target: Wednesday
                    break;
                case 6: // Saturday
                    targetDayIndex = 5; // Target: Friday
                    break;
            }

            const pickupDate = findNextAssignedDate(targetDayIndex);

            // Format the date
            assignedDate = pickupDate.toLocaleDateString('en-KE', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });

            // Store the calculated date in localStorage
            localStorage.setItem(PICKUP_DATE_STORAGE_KEY, assignedDate);
            
            // Display the newly assigned date
            dayEl.innerText = `✅ Your leaving certificate pickup date is due on: ${assignedDate}`;

        } else {
             // Clearance Incomplete and no date has been assigned yet
             dayEl.innerText = `⚠️ Clearance Incomplete. Current Progress: ${avg}%`;
        }
    }
}

calculateTotalPercentage();
// The timeout is now safe because the date logic is guarded by localStorage
setTimeout(calculateTotalPercentage, 3000);