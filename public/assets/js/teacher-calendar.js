/**
 * Teacher Calendar Component
 * Preply-style calendar with drag-and-drop slot creation
 */

(function() {
    'use strict';
    
    class TeacherCalendar {
        constructor(containerId, options = {}) {
            this.container = document.getElementById(containerId);
            if (!this.container) {
                console.error('Calendar container not found:', containerId);
                return;
            }
            
            this.options = {
                teacherId: options.teacherId || null,
                timezone: options.timezone || window.userTimezone || 'UTC',
                slotDuration: options.slotDuration || 30, // minutes
                onSlotCreated: options.onSlotCreated || null,
                onSlotUpdated: options.onSlotUpdated || null,
                onSlotDeleted: options.onSlotDeleted || null,
                ...options
            };
            
            this.currentWeek = this.getStartOfWeek(new Date());
            this.availabilitySlots = [];
            this.lessons = [];
            this.timeOffs = [];
            this.dragging = false;
            this.dragStart = null;
            this.dragEnd = null;
            this.isCreatingSlot = false;
            this.selectedSlot = null;
            
            this.init();
        }
        
        init() {
            this.render();
            this.loadAvailability();
            this.loadLessons();
            this.loadTimeOffs();
            this.attachEventListeners();
        }
        
        getStartOfWeek(date) {
            const d = new Date(date);
            const day = d.getDay();
            // Get Monday of the week (day 1)
            // If Sunday (day 0), go back 6 days; otherwise go back (day - 1) days
            const diff = day === 0 ? -6 : 1 - day;
            d.setDate(d.getDate() + diff);
            // Set to start of day
            d.setHours(0, 0, 0, 0);
            return d;
        }
        
        getWeekDays() {
            const days = [];
            const start = new Date(this.currentWeek);
            for (let i = 0; i < 7; i++) {
                const date = new Date(start);
                date.setDate(start.getDate() + i);
                days.push(date);
            }
            return days;
        }
        
        generateTimeSlots() {
            const slots = [];
            for (let hour = 0; hour < 24; hour++) {
                for (let minute = 0; minute < 60; minute += this.options.slotDuration) {
                    slots.push({
                        hour: hour,
                        minute: minute,
                        display: `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
                    });
                }
            }
            return slots;
        }
        
        formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        formatTime(hour, minute) {
            return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00`;
        }
        
        render() {
            const weekDays = this.getWeekDays();
            const timeSlots = this.generateTimeSlots();
            
            let html = `
                <div class="teacher-calendar-header">
                    <div class="calendar-controls">
                        <button class="calendar-nav-btn" data-action="prev-week">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <h2>
                            ${weekDays[0].toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} – 
                            ${weekDays[6].toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                        </h2>
                        <button class="calendar-nav-btn" data-action="next-week">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button class="calendar-nav-btn" data-action="today" style="margin-left: 15px;">
                            Today
                        </button>
                    </div>
                    <div class="calendar-actions">
                        <button class="btn-primary" id="add-weekly-slot-btn">
                            <i class="fas fa-plus"></i> Add Weekly Slot
                        </button>
                        <button class="btn-secondary" id="add-onetime-slot-btn">
                            <i class="fas fa-calendar-plus"></i> Add One-Time Slot
                        </button>
                    </div>
                </div>
                <div class="teacher-calendar-grid">
                    <div class="calendar-time-column">
                        <div class="time-header">
                            <span style="font-size: 0.7rem; color: #999;">TIME</span>
                        </div>
                        ${timeSlots.map(slot => `
                            <div class="time-slot-header" data-hour="${slot.hour}" data-minute="${slot.minute}">
                                ${slot.display}
                            </div>
                        `).join('')}
                    </div>
                    <div class="calendar-days-container">
                        ${weekDays.map((day, dayIndex) => {
                            const dayName = day.toLocaleDateString('en-US', { weekday: 'short' }).toUpperCase();
                            const dayNumber = day.getDate();
                            const monthName = day.toLocaleDateString('en-US', { month: 'short' });
                            return `
                            <div class="calendar-day-column" data-date="${this.formatDate(day)}" data-day-index="${dayIndex}">
                                <div class="day-header">
                                    <div class="day-name">${dayName}</div>
                                    <div class="day-number">${dayNumber}</div>
                                    <div class="day-month" style="font-size: 0.7rem; color: #999; margin-top: 2px;">${monthName}</div>
                                </div>
                                <div class="day-time-slots" data-date="${this.formatDate(day)}">
                                    ${timeSlots.map(slot => `
                                        <div class="time-slot-cell" 
                                             data-date="${this.formatDate(day)}"
                                             data-hour="${slot.hour}"
                                             data-minute="${slot.minute}"
                                             data-time="${slot.display}">
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
            
            this.container.innerHTML = html;
            this.renderSlots();
            this.attachEventListeners();
            
            // Ensure available cells are marked after rendering
            this.markAvailableCells();
        }
        
        markAvailableCells() {
            const weekDays = this.getWeekDays();
            
            weekDays.forEach(day => {
                const dayColumn = this.container.querySelector(`[data-date="${this.formatDate(day)}"]`);
                const slotsContainer = dayColumn?.querySelector('.day-time-slots');
                if (!slotsContainer) return;
                
                const dayOfWeek = day.toLocaleDateString('en-US', { weekday: 'long' });
                const dateStr = this.formatDate(day);
                const timeSlotCells = slotsContainer.querySelectorAll('.time-slot-cell');
                
                // Reset all cells first
                timeSlotCells.forEach(cell => {
                    cell.classList.remove('available');
                });
                
                // Mark cells within availability slots as available
                this.availabilitySlots.forEach(slot => {
                    if (slot.is_available && (slot.day_of_week === dayOfWeek || (slot.specific_date && slot.specific_date === dateStr))) {
                        const startTime = slot.start_time.split(':');
                        const endTime = slot.end_time.split(':');
                        const startHour = parseInt(startTime[0]);
                        const startMinute = parseInt(startTime[1]);
                        const endHour = parseInt(endTime[0]);
                        const endMinute = parseInt(endTime[1]);
                        
                        const startMinutes = startHour * 60 + startMinute;
                        const endMinutes = endHour * 60 + endMinute;
                        
                        // Mark all time slot cells within this availability range as available
                        timeSlotCells.forEach(cell => {
                            const cellHour = parseInt(cell.dataset.hour);
                            const cellMinute = parseInt(cell.dataset.minute);
                            const cellMinutes = cellHour * 60 + cellMinute;
                            
                            if (cellMinutes >= startMinutes && cellMinutes < endMinutes) {
                                cell.classList.add('available');
                            }
                        });
                    }
                });
            });
        }
        
        renderSlots() {
            const weekDays = this.getWeekDays();
            
            weekDays.forEach(day => {
                const dayColumn = this.container.querySelector(`[data-date="${this.formatDate(day)}"]`);
                const slotsContainer = dayColumn?.querySelector('.day-time-slots');
                if (!slotsContainer) return;
                
                // Clear existing slot blocks
                slotsContainer.querySelectorAll('.availability-slot, .lesson-block, .time-off-period').forEach(el => el.remove());
                
                const dayOfWeek = day.toLocaleDateString('en-US', { weekday: 'long' });
                const dateStr = this.formatDate(day);
                
                // Render availability slot blocks (for editing/deleting)
                // Note: Available cells are marked in markAvailableCells() function
                this.availabilitySlots.forEach(slot => {
                    if (slot.day_of_week === dayOfWeek || (slot.specific_date && slot.specific_date === dateStr)) {
                        const startTime = slot.start_time.split(':');
                        const endTime = slot.end_time.split(':');
                        const startHour = parseInt(startTime[0]);
                        const startMinute = parseInt(startTime[1]);
                        const endHour = parseInt(endTime[0]);
                        const endMinute = parseInt(endTime[1]);
                        
                        const startMinutes = startHour * 60 + startMinute;
                        const endMinutes = endHour * 60 + endMinute;
                        const duration = endMinutes - startMinutes;
                        const slotHeight = (duration / this.options.slotDuration) * 60; // 60px per 30min slot
                        const topOffset = (startMinutes / this.options.slotDuration) * 60;
                        
                        const slotEl = document.createElement('div');
                        slotEl.className = `availability-slot ${slot.is_available ? 'available' : 'unavailable'}`;
                        slotEl.style.top = `${topOffset}px`;
                        slotEl.style.height = `${slotHeight}px`;
                        slotEl.dataset.slotId = slot.id;
                        slotEl.dataset.dayOfWeek = slot.day_of_week;
                        slotEl.dataset.specificDate = slot.specific_date || '';
                        slotEl.dataset.isRecurring = slot.specific_date ? '0' : '1';
                        
                        slotEl.innerHTML = `
                            <div class="slot-content">
                                <div class="slot-time">${slot.start_time.substring(0, 5)} - ${slot.end_time.substring(0, 5)}</div>
                                <div class="slot-type">${slot.specific_date ? 'One-time' : 'Weekly'}</div>
                                <div class="slot-actions">
                                    <button class="slot-edit-btn" data-slot-id="${slot.id}"><i class="fas fa-edit"></i></button>
                                    <button class="slot-delete-btn" data-slot-id="${slot.id}"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        `;
                        
                        slotsContainer.appendChild(slotEl);
                    }
                });
                
                // Render lessons
                this.lessons.forEach(lesson => {
                    if (lesson.lesson_date === dateStr) {
                        const startTime = lesson.start_time.split(':');
                        const endTime = lesson.end_time.split(':');
                        const startHour = parseInt(startTime[0]);
                        const startMinute = parseInt(startTime[1]);
                        const endHour = parseInt(endTime[0]);
                        const endMinute = parseInt(endTime[1]);
                        
                        const startMinutes = startHour * 60 + startMinute;
                        const endMinutes = endHour * 60 + endMinute;
                        const duration = endMinutes - startMinutes;
                        const slotHeight = (duration / this.options.slotDuration) * 60;
                        const topOffset = (startMinutes / this.options.slotDuration) * 60;
                        
                        const lessonEl = document.createElement('div');
                        lessonEl.className = 'lesson-block';
                        
                        // Determine lesson type for styling
                        const isRecurring = lesson.recurring_lesson_id || lesson.lesson_type === 'recurring';
                        const isFirstLesson = lesson.is_first_lesson || (isRecurring && lesson.series_start_date && lesson.lesson_date === lesson.series_start_date);
                        
                        // Add appropriate classes
                        if (isFirstLesson) {
                            lessonEl.classList.add('lesson-first');
                        } else if (isRecurring) {
                            lessonEl.classList.add('lesson-weekly');
                        } else {
                            lessonEl.classList.add('lesson-onetime');
                        }
                        
                        lessonEl.style.top = `${topOffset}px`;
                        lessonEl.style.height = `${slotHeight}px`;
                        lessonEl.dataset.lessonId = lesson.id;
                        
                        // Check if this is a test class
                        const isTestClass = (lesson.student_email && lesson.student_email.toLowerCase() === 'student@statenacademy.com');
                        const displayName = isTestClass ? 'Test Class' : (lesson.student_name || 'Student');
                        
                        lessonEl.innerHTML = `
                            <div class="lesson-content">
                                <div class="lesson-student">${displayName}</div>
                                <div class="lesson-time">${lesson.start_time.substring(0, 5)} - ${lesson.end_time.substring(0, 5)}</div>
                            </div>
                        `;
                        
                        slotsContainer.appendChild(lessonEl);
                    }
                });
                
                // Render time-off periods
                this.timeOffs.forEach(timeOff => {
                    const timeOffStart = new Date(timeOff.start_date + 'T00:00:00');
                    const timeOffEnd = new Date(timeOff.end_date + 'T23:59:59');
                    const dayDate = new Date(dateStr + 'T00:00:00');
                    
                    // Check if this day falls within the time-off period
                    if (dayDate >= timeOffStart && dayDate <= timeOffEnd) {
                        const timeOffEl = document.createElement('div');
                        timeOffEl.className = 'time-off-period';
                        timeOffEl.style.top = '0px';
                        timeOffEl.style.height = '1440px'; // Full day
                        timeOffEl.style.zIndex = '1';
                        timeOffEl.title = timeOff.reason || 'Time Off';
                        
                        timeOffEl.innerHTML = `
                            <div style="padding: 5px; text-align: center;">
                                <div style="font-weight: 600;">Time Off</div>
                                ${timeOff.reason ? `<div style="font-size: 0.7rem; margin-top: 2px;">${timeOff.reason}</div>` : ''}
                            </div>
                        `;
                        
                        slotsContainer.appendChild(timeOffEl);
                    }
                });
            });
        }
        
        attachEventListeners() {
            // Navigation buttons
            this.container.querySelectorAll('.calendar-nav-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const action = e.currentTarget.dataset.action;
                    switch(action) {
                        case 'prev-week':
                            this.currentWeek.setDate(this.currentWeek.getDate() - 7);
                            this.render();
                            this.loadAvailability();
                            this.loadLessons();
                            this.loadTimeOffs();
                            break;
                        case 'next-week':
                            this.currentWeek.setDate(this.currentWeek.getDate() + 7);
                            this.render();
                            this.loadAvailability();
                            this.loadLessons();
                            this.loadTimeOffs();
                            break;
                        case 'today':
                            this.currentWeek = this.getStartOfWeek(new Date());
                            this.render();
                            this.loadAvailability();
                            this.loadLessons();
                            this.loadTimeOffs();
                            break;
                    }
                });
            });
            
            // Add slot buttons
            const addWeeklyBtn = document.getElementById('add-weekly-slot-btn');
            const addOnetimeBtn = document.getElementById('add-onetime-slot-btn');
            
            if (addWeeklyBtn) {
                addWeeklyBtn.addEventListener('click', () => this.showSlotModal('weekly'));
            }
            if (addOnetimeBtn) {
                addOnetimeBtn.addEventListener('click', () => this.showSlotModal('onetime'));
            }
            
            // Drag and drop for creating slots
            this.setupDragAndDrop();
            
            // Slot action buttons
            this.container.addEventListener('click', (e) => {
                if (e.target.closest('.slot-edit-btn')) {
                    const slotId = e.target.closest('.slot-edit-btn').dataset.slotId;
                    this.editSlot(slotId);
                }
                if (e.target.closest('.slot-delete-btn')) {
                    const slotId = e.target.closest('.slot-delete-btn').dataset.slotId;
                    this.deleteSlot(slotId);
                }
            });
        }
        
        setupDragAndDrop() {
            const dayColumns = this.container.querySelectorAll('.day-time-slots');
            
            dayColumns.forEach(column => {
                let isDragging = false;
                let startCell = null;
                let endCell = null;
                
                column.addEventListener('mousedown', (e) => {
                    const cell = e.target.closest('.time-slot-cell');
                    if (!cell || e.target.closest('.availability-slot, .lesson-block')) return;
                    
                    isDragging = true;
                    startCell = cell;
                    this.dragging = true;
                    this.dragStart = {
                        date: cell.dataset.date,
                        hour: parseInt(cell.dataset.hour),
                        minute: parseInt(cell.dataset.minute)
                    };
                    // Initialize endCell so a simple click (no move) still works
                    endCell = cell;
                    this.dragEnd = { ...this.dragStart };
                    
                    // Add dragging class to cell
                    cell.classList.add('dragging');
                    
                    e.preventDefault();
                });
                
                column.addEventListener('mousemove', (e) => {
                    if (!isDragging) return;
                    
                    const cell = e.target.closest('.time-slot-cell');
                    if (cell) {
                        // Remove dragging from all cells
                        column.querySelectorAll('.time-slot-cell').forEach(c => c.classList.remove('dragging'));
                        
                        // Mark cells in range as dragging
                        const startMinutes = this.dragStart.hour * 60 + this.dragStart.minute;
                        const cellMinutes = parseInt(cell.dataset.hour) * 60 + parseInt(cell.dataset.minute);
                        const minMinutes = Math.min(startMinutes, cellMinutes);
                        const maxMinutes = Math.max(startMinutes, cellMinutes);
                        
                        column.querySelectorAll('.time-slot-cell').forEach(c => {
                            const cMinutes = parseInt(c.dataset.hour) * 60 + parseInt(c.dataset.minute);
                            if (cMinutes >= minMinutes && cMinutes <= maxMinutes) {
                                c.classList.add('dragging');
                            }
                        });
                        
                        if (cell !== startCell) {
                            endCell = cell;
                            this.dragEnd = {
                                date: cell.dataset.date,
                                hour: parseInt(cell.dataset.hour),
                                minute: parseInt(cell.dataset.minute)
                            };
                        }
                    }
                });
                
                column.addEventListener('mouseup', (e) => {
                    // Remove dragging class from all cells
                    column.querySelectorAll('.time-slot-cell').forEach(c => c.classList.remove('dragging'));
                    
                    // If user clicked without moving, ensure endCell is set
                    if (!endCell && startCell) {
                        endCell = startCell;
                        this.dragEnd = { ...this.dragStart };
                    }

                    if (isDragging && startCell && endCell) {
                        this.handleSlotCreation();
                    }
                    isDragging = false;
                    this.dragging = false;
                    startCell = null;
                    endCell = null;
                });
                
                // Also handle mouseleave to clean up
                column.addEventListener('mouseleave', () => {
                    if (isDragging) {
                        column.querySelectorAll('.time-slot-cell').forEach(c => c.classList.remove('dragging'));
                    }
                });
            });
        }
        
        handleSlotCreation() {
            if (!this.dragStart || !this.dragEnd) return;
            
            // Show modal with 3 options: Open Slot, Book Lesson, Book Time Off
            this.showActionSelectionModal();
        }
        
        showActionSelectionModal() {
            // Calculate time range
            const startTime = this.formatTime(this.dragStart.hour, this.dragStart.minute);
            const endTime = this.formatTime(this.dragEnd.hour, this.dragEnd.minute);
            const dateStr = this.dragStart.date;
            const dateObj = new Date(dateStr + 'T00:00:00');
            const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
            const dateDisplay = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            
            const modal = document.createElement('div');
            modal.className = 'action-selection-modal-overlay';
            modal.innerHTML = `
                <div class="action-selection-modal">
                    <div class="modal-header">
                        <h3>Select Action</h3>
                        <button class="modal-close-btn" onclick="this.closest('.action-selection-modal-overlay').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-time-info">
                        <p><strong>Selected Time:</strong> ${dateDisplay} (${dayName})</p>
                        <p><strong>Time Range:</strong> ${startTime.substring(0, 5)} - ${endTime.substring(0, 5)}</p>
                    </div>
                    <div class="action-buttons">
                        <button class="action-btn action-open-slot" data-action="open-slot">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Open Slot</span>
                            <small>Make this time available for booking</small>
                        </button>
                        <button class="action-btn action-book-lesson" data-action="book-lesson">
                            <i class="fas fa-user-graduate"></i>
                            <span>Book Lesson</span>
                            <small>Schedule a lesson with a student</small>
                        </button>
                        <button class="action-btn action-time-off" data-action="time-off">
                            <i class="fas fa-ban"></i>
                            <span>Book Time Off</span>
                            <small>Mark this time as unavailable</small>
                        </button>
                    </div>
                    <div class="modal-actions">
                        <button class="btn-secondary" onclick="this.closest('.action-selection-modal-overlay').remove()">Cancel</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Handle action selection
            modal.querySelectorAll('.action-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const action = btn.dataset.action;
                    modal.remove();
                    this.handleSelectedAction(action);
                });
            });
            
            // Close on overlay click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        }
        
        async handleSelectedAction(action) {
            const startTime = this.formatTime(this.dragStart.hour, this.dragStart.minute);
            const endTime = this.formatTime(this.dragEnd.hour, this.dragEnd.minute);
            const dateStr = this.dragStart.date;
            
            switch(action) {
                case 'open-slot':
                    this.handleOpenSlot(dateStr, startTime, endTime);
                    break;
                case 'book-lesson':
                    await this.handleBookLesson(dateStr, startTime, endTime);
                    break;
                case 'time-off':
                    this.handleTimeOff(dateStr, startTime, endTime);
                    break;
            }
        }
        
        handleOpenSlot(dateStr, startTime, endTime) {
            // Ask if weekly or one-time
            const isWeekly = confirm('Create as weekly recurring slot? (Click OK for weekly, Cancel for one-time)');
            
            if (isWeekly) {
                const dayOfWeek = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
                this.createWeeklySlot(dayOfWeek, startTime, endTime);
            } else {
                this.createOneTimeSlot(dateStr, startTime, endTime);
            }
        }
        
        async handleBookLesson(dateStr, startTime, endTime) {
            // Fetch teacher's students
            const students = await this.fetchTeacherStudents();
            
            if (!students || students.length === 0) {
                alert('You don\'t have any students yet. Students will appear here once they book lessons with you.');
                return;
            }
            
            // Show student selection modal
            const modal = document.createElement('div');
            modal.className = 'action-selection-modal-overlay';
            modal.innerHTML = `
                <div class="action-selection-modal" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3>Book Lesson</h3>
                        <button class="modal-close-btn" onclick="this.closest('.action-selection-modal-overlay').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-time-info">
                        <p><strong>Date:</strong> ${new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                        <p><strong>Time:</strong> ${startTime.substring(0, 5)} - ${endTime.substring(0, 5)}</p>
                    </div>
                    <div class="form-group">
                        <label>Select Student:</label>
                        <select id="student-select" class="form-control" required>
                            <option value="">-- Select a student --</option>
                            ${students.map(s => `<option value="${s.id}">${s.name} (${s.email})</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="recurring-lesson">
                            Book as recurring weekly lesson
                        </label>
                    </div>
                    <div id="recurring-options" style="display: none; margin-top: 15px;">
                        <div class="form-group">
                            <label>Number of weeks:</label>
                            <input type="number" id="number-of-weeks" min="2" max="52" value="12" style="width: 100px;">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="btn-primary" id="confirm-book-lesson">Book Lesson</button>
                        <button class="btn-secondary" onclick="this.closest('.action-selection-modal-overlay').remove()">Cancel</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Handle recurring checkbox
            modal.querySelector('#recurring-lesson').addEventListener('change', function() {
                modal.querySelector('#recurring-options').style.display = this.checked ? 'block' : 'none';
            });
            
            // Handle confirmation
            modal.querySelector('#confirm-book-lesson').addEventListener('click', async () => {
                const studentId = modal.querySelector('#student-select').value;
                if (!studentId) {
                    alert('Please select a student');
                    return;
                }
                
                const isRecurring = modal.querySelector('#recurring-lesson').checked;
                const numberOfWeeks = isRecurring ? parseInt(modal.querySelector('#number-of-weeks').value) : 1;
                
                modal.remove();
                await this.createLessonForStudent(dateStr, startTime, endTime, studentId, isRecurring, numberOfWeeks);
            });
        }
        
        async fetchTeacherStudents() {
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            const apiPath = basePath + '/api/calendar.php';
            
            try {
                const response = await fetch(`${apiPath}?action=get-students`);
                const data = await response.json();
                if (data.success && data.students) {
                    return data.students;
                }
                return [];
            } catch (error) {
                console.error('Failed to fetch students:', error);
                return [];
            }
        }
        
        async createLessonForStudent(dateStr, startTime, endTime, studentId, isRecurring, numberOfWeeks) {
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            const apiPath = basePath + '/api/calendar.php';
            
            const loadingEl = document.getElementById('calendar-loading');
            if (loadingEl) loadingEl.style.display = 'block';
            
            try {
                if (isRecurring) {
                    const dayOfWeek = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
                    const endDate = new Date(dateStr);
                    endDate.setDate(endDate.getDate() + (numberOfWeeks * 7));
                    
                    const response = await fetch(apiPath + '?action=book-recurring', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            teacher_id: this.options.teacherId,
                            student_id: studentId,
                            day_of_week: dayOfWeek,
                            start_time: startTime,
                            end_time: endTime,
                            start_date: dateStr,
                            end_date: this.formatDate(endDate),
                            number_of_weeks: numberOfWeeks,
                            frequency_weeks: 1
                        })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        await this.loadLessons();
                        this.render();
                        alert('Recurring lesson booked successfully!');
                    } else {
                        alert('Error: ' + (data.error || 'Failed to book lesson'));
                    }
                } else {
                    // Single lesson booking
                    const response = await fetch(apiPath + '?action=book-lesson', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            teacher_id: this.options.teacherId,
                            student_id: studentId,
                            lesson_date: dateStr,
                            start_time: startTime,
                            end_time: endTime
                        })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        await this.loadLessons();
                        this.render();
                        alert('Lesson booked successfully!');
                    } else {
                        alert('Error: ' + (data.error || 'Failed to book lesson'));
                    }
                }
            } catch (error) {
                console.error('Failed to book lesson:', error);
                alert('Error booking lesson: ' + error.message);
            } finally {
                if (loadingEl) loadingEl.style.display = 'none';
            }
        }
        
        handleTimeOff(dateStr, startTime, endTime) {
            // For time off, we need to create a time-off entry
            // Since time-off is date-based, we'll use the selected date
            const modal = document.createElement('div');
            modal.className = 'action-selection-modal-overlay';
            modal.innerHTML = `
                <div class="action-selection-modal" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3>Book Time Off</h3>
                        <button class="modal-close-btn" onclick="this.closest('.action-selection-modal-overlay').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-time-info">
                        <p><strong>Date:</strong> ${new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                        <p><strong>Time:</strong> ${startTime.substring(0, 5)} - ${endTime.substring(0, 5)}</p>
                    </div>
                    <div class="form-group">
                        <label>Reason (optional):</label>
                        <input type="text" id="time-off-reason" class="form-control" placeholder="e.g., Personal time, Holiday, etc.">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="full-day-time-off">
                            Mark entire day as time off
                        </label>
                    </div>
                    <div class="modal-actions">
                        <button class="btn-primary" id="confirm-time-off">Book Time Off</button>
                        <button class="btn-secondary" onclick="this.closest('.action-selection-modal-overlay').remove()">Cancel</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            modal.querySelector('#confirm-time-off').addEventListener('click', async () => {
                const reason = modal.querySelector('#time-off-reason').value;
                const isFullDay = modal.querySelector('#full-day-time-off').checked;
                
                let startDate = dateStr;
                let endDate = dateStr;
                
                if (isFullDay) {
                    // Full day time off
                    startDate = dateStr + ' 00:00:00';
                    endDate = dateStr + ' 23:59:59';
                } else {
                    // Specific time range
                    startDate = dateStr + ' ' + startTime;
                    endDate = dateStr + ' ' + endTime;
                }
                
                modal.remove();
                await this.createTimeOff(startDate, endDate, reason);
            });
        }
        
        async createTimeOff(startDate, endDate, reason) {
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            const apiPath = basePath + '/api/calendar.php';
            
            const loadingEl = document.getElementById('calendar-loading');
            if (loadingEl) loadingEl.style.display = 'block';
            
            try {
                const response = await fetch(apiPath + '?action=time-off', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        start_date: startDate.split(' ')[0],
                        end_date: endDate.split(' ')[0],
                        reason: reason || null
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    await this.loadAvailability();
                    await this.loadLessons();
                    await this.loadTimeOffs();
                    this.render();
                    alert('Time off booked successfully!');
                } else {
                    alert('Error: ' + (data.error || 'Failed to book time off'));
                }
            } catch (error) {
                console.error('Failed to book time off:', error);
                alert('Error booking time off: ' + error.message);
            } finally {
                if (loadingEl) loadingEl.style.display = 'none';
            }
        }
        
        showSlotModal(type) {
            // Simple modal for slot creation
            const modal = document.createElement('div');
            modal.className = 'slot-modal-overlay';
            modal.innerHTML = `
                <div class="slot-modal">
                    <h3>Add ${type === 'weekly' ? 'Weekly' : 'One-Time'} Slot</h3>
                    <form id="slot-form">
                        ${type === 'weekly' ? `
                            <div class="form-group">
                                <label>Day of Week</label>
                                <select name="day_of_week" required>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                            </div>
                        ` : `
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="specific_date" required min="${this.formatDate(new Date())}">
                            </div>
                        `}
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" required>
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Add Slot</button>
                            <button type="button" class="btn-secondary" onclick="this.closest('.slot-modal-overlay').remove()">Cancel</button>
                        </div>
                    </form>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            modal.querySelector('#slot-form').addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                
                if (type === 'weekly') {
                    this.createWeeklySlot(
                        formData.get('day_of_week'),
                        formData.get('start_time') + ':00',
                        formData.get('end_time') + ':00'
                    );
                } else {
                    this.createOneTimeSlot(
                        formData.get('specific_date'),
                        formData.get('start_time') + ':00',
                        formData.get('end_time') + ':00'
                    );
                }
                
                modal.remove();
            });
        }
        
        async createWeeklySlot(dayOfWeek, startTime, endTime) {
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            const apiPath = basePath + '/api/calendar.php';
            
            // Show loading state
            const loadingEl = document.getElementById('calendar-loading');
            if (loadingEl) loadingEl.style.display = 'block';
            
            try {
                const response = await fetch(apiPath + '?action=create-availability', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        day_of_week: dayOfWeek,
                        start_time: startTime,
                        end_time: endTime,
                        is_recurring: true
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    await this.loadAvailability();
                    this.render();
                    if (this.options.onSlotCreated) {
                        this.options.onSlotCreated(data.slot);
                    }
                } else {
                    alert('Error: ' + (data.error || 'Failed to create slot'));
                }
            } catch (error) {
                console.error('Failed to create slot:', error);
                alert('Error creating slot: ' + error.message);
            } finally {
                // Hide loading state
                if (loadingEl) loadingEl.style.display = 'none';
            }
        }
        
        async createOneTimeSlot(date, startTime, endTime) {
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            const apiPath = basePath + '/api/calendar.php';
            
            // Show loading state
            const loadingEl = document.getElementById('calendar-loading');
            if (loadingEl) loadingEl.style.display = 'block';
            
            try {
                const response = await fetch(apiPath + '?action=create-availability', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        specific_date: date,
                        start_time: startTime,
                        end_time: endTime,
                        is_recurring: false
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    await this.loadAvailability();
                    this.render();
                    if (this.options.onSlotCreated) {
                        this.options.onSlotCreated(data.slot);
                    }
                } else {
                    alert('Error: ' + (data.error || 'Failed to create slot'));
                }
            } catch (error) {
                console.error('Failed to create slot:', error);
                alert('Error creating slot: ' + error.message);
            } finally {
                // Hide loading state
                if (loadingEl) loadingEl.style.display = 'none';
            }
        }
        
        async editSlot(slotId) {
            const slot = this.availabilitySlots.find(s => s.id == slotId);
            if (!slot) return;
            
            this.showSlotModal(slot.specific_date ? 'onetime' : 'weekly', slot);
        }
        
        async deleteSlot(slotId) {
            if (!confirm('Are you sure you want to delete this slot?')) return;
            
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            const apiPath = basePath + '/api/calendar.php';
            
            // Show loading state
            const loadingEl = document.getElementById('calendar-loading');
            if (loadingEl) loadingEl.style.display = 'block';
            
            try {
                const response = await fetch(apiPath + '?action=delete-availability&id=' + slotId, {
                    method: 'DELETE'
                });
                
                const data = await response.json();
                if (data.success) {
                    await this.loadAvailability();
                    this.render();
                    if (this.options.onSlotDeleted) {
                        this.options.onSlotDeleted(slotId);
                    }
                } else {
                    alert('Error: ' + (data.error || 'Failed to delete slot'));
                }
            } catch (error) {
                console.error('Failed to delete slot:', error);
                alert('Error deleting slot: ' + error.message);
            } finally {
                // Hide loading state
                if (loadingEl) loadingEl.style.display = 'none';
            }
        }
        
        async loadAvailability() {
            if (!this.options.teacherId) return;
            
            const weekDays = this.getWeekDays();
            const dateFrom = this.formatDate(weekDays[0]);
            const dateTo = this.formatDate(weekDays[6]);
            
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            const apiPath = basePath + '/api/calendar.php';
            
            try {
                const response = await fetch(
                    `${apiPath}?action=get-availability&teacher_id=${this.options.teacherId}&date_from=${dateFrom}&date_to=${dateTo}`
                );
                const data = await response.json();
                if (data.success && data.slots) {
                    this.availabilitySlots = data.slots;
                    // Re-mark available cells after loading
                    this.markAvailableCells();
                }
            } catch (error) {
                console.error('Failed to load availability:', error);
            }
        }
        
        async loadLessons() {
            if (!this.options.teacherId) return;
            
            const weekDays = this.getWeekDays();
            const dateFrom = this.formatDate(weekDays[0]);
            const dateTo = this.formatDate(weekDays[6]);
            
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            const apiPath = basePath + '/api/calendar.php';
            
            try {
                const response = await fetch(
                    `${apiPath}?action=lessons&user_id=${this.options.teacherId}&date_from=${dateFrom}&date_to=${dateTo}&role=teacher`
                );
                const data = await response.json();
                if (data.success && data.lessons) {
                    this.lessons = data.lessons;
                }
            } catch (error) {
                console.error('Failed to load lessons:', error);
            }
        }
    }
    
    // Export to window
    window.TeacherCalendar = TeacherCalendar;
})();

