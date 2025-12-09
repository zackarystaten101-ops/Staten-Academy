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
            this.attachEventListeners();
        }
        
        getStartOfWeek(date) {
            const d = new Date(date);
            const day = d.getDay();
            const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Monday as first day
            return new Date(d.setDate(diff));
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
                            ${weekDays[0].toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - 
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
                        <div class="time-header"></div>
                        ${timeSlots.map(slot => `
                            <div class="time-slot-header" data-hour="${slot.hour}" data-minute="${slot.minute}">
                                ${slot.display}
                            </div>
                        `).join('')}
                    </div>
                    <div class="calendar-days-container">
                        ${weekDays.map((day, dayIndex) => `
                            <div class="calendar-day-column" data-date="${this.formatDate(day)}" data-day-index="${dayIndex}">
                                <div class="day-header">
                                    <div class="day-name">${day.toLocaleDateString('en-US', { weekday: 'short' })}</div>
                                    <div class="day-number">${day.getDate()}</div>
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
                        `).join('')}
                    </div>
                </div>
            `;
            
            this.container.innerHTML = html;
            this.renderSlots();
            this.attachEventListeners();
        }
        
        renderSlots() {
            const weekDays = this.getWeekDays();
            
            weekDays.forEach(day => {
                const dayColumn = this.container.querySelector(`[data-date="${this.formatDate(day)}"]`);
                const slotsContainer = dayColumn?.querySelector('.day-time-slots');
                if (!slotsContainer) return;
                
                // Clear existing slot blocks
                slotsContainer.querySelectorAll('.availability-slot, .lesson-block').forEach(el => el.remove());
                
                const dayOfWeek = day.toLocaleDateString('en-US', { weekday: 'long' });
                const dateStr = this.formatDate(day);
                
                // Render availability slots
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
                            break;
                        case 'next-week':
                            this.currentWeek.setDate(this.currentWeek.getDate() + 7);
                            this.render();
                            this.loadAvailability();
                            this.loadLessons();
                            break;
                        case 'today':
                            this.currentWeek = this.getStartOfWeek(new Date());
                            this.render();
                            this.loadAvailability();
                            this.loadLessons();
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
                    
                    e.preventDefault();
                });
                
                column.addEventListener('mousemove', (e) => {
                    if (!isDragging) return;
                    
                    const cell = e.target.closest('.time-slot-cell');
                    if (cell && cell !== startCell) {
                        endCell = cell;
                        this.dragEnd = {
                            date: cell.dataset.date,
                            hour: parseInt(cell.dataset.hour),
                            minute: parseInt(cell.dataset.minute)
                        };
                    }
                });
                
                column.addEventListener('mouseup', (e) => {
                    if (isDragging && startCell && endCell) {
                        this.handleSlotCreation();
                    }
                    isDragging = false;
                    this.dragging = false;
                    startCell = null;
                    endCell = null;
                });
            });
        }
        
        handleSlotCreation() {
            if (!this.dragStart || !this.dragEnd) return;
            
            // Determine if it's a weekly or one-time slot based on selection
            const isWeekly = confirm('Create as weekly recurring slot? (Click OK for weekly, Cancel for one-time)');
            
            const startTime = this.formatTime(this.dragStart.hour, this.dragStart.minute);
            const endTime = this.formatTime(this.dragEnd.hour, this.dragEnd.minute);
            
            if (isWeekly) {
                const dayOfWeek = new Date(this.dragStart.date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
                this.createWeeklySlot(dayOfWeek, startTime, endTime);
            } else {
                this.createOneTimeSlot(this.dragStart.date, startTime, endTime);
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

