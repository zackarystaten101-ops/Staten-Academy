/**
 * Calendar Component
 * Preply-style calendar with timezone support, recurring bookings, and color coding
 */

(function() {
    'use strict';
    
    class Calendar {
        constructor(containerId, options = {}) {
            this.container = document.getElementById(containerId);
            if (!this.container) {
                console.error('Calendar container not found:', containerId);
                return;
            }
            
            this.options = {
                view: options.view || 'month', // month, week, day
                timezone: options.timezone || window.userTimezone || 'UTC',
                teacherId: options.teacherId || null,
                studentId: options.studentId || null,
                onSlotSelect: options.onSlotSelect || null,
                onLessonClick: options.onLessonClick || null,
                ...options
            };
            
            this.currentDate = new Date();
            this.lessons = [];
            this.availability = [];
            this.init();
        }
        
        init() {
            this.render();
            this.loadLessons();
            if (this.options.teacherId) {
                this.loadAvailability();
            }
        }
        
        render() {
            const view = this.options.view;
            if (view === 'month') {
                this.renderMonthView();
            } else if (view === 'week') {
                this.renderWeekView();
            } else {
                this.renderDayView();
            }
        }
        
        renderMonthView() {
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - startDate.getDay());
            
            let html = `
                <div class="calendar-header">
                    <button class="calendar-nav-btn" onclick="calendar.prevMonth()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h2>${this.currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}</h2>
                    <button class="calendar-nav-btn" onclick="calendar.nextMonth()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="calendar-grid month-view">
                    <div class="calendar-weekdays">
                        <div class="calendar-weekday">Sun</div>
                        <div class="calendar-weekday">Mon</div>
                        <div class="calendar-weekday">Tue</div>
                        <div class="calendar-weekday">Wed</div>
                        <div class="calendar-weekday">Thu</div>
                        <div class="calendar-weekday">Fri</div>
                        <div class="calendar-weekday">Sat</div>
                    </div>
                    <div class="calendar-days">
            `;
            
            const currentDate = new Date(startDate);
            for (let i = 0; i < 42; i++) {
                const dateStr = this.formatDate(currentDate);
                const dayLessons = this.getLessonsForDate(dateStr);
                const isCurrentMonth = currentDate.getMonth() === month;
                const isToday = this.isToday(currentDate);
                
                html += `
                    <div class="calendar-day ${!isCurrentMonth ? 'other-month' : ''} ${isToday ? 'today' : ''}" 
                         data-date="${dateStr}">
                        <div class="day-number">${currentDate.getDate()}</div>
                        <div class="day-lessons">
                            ${dayLessons.map(lesson => `
                                <div class="lesson-indicator" 
                                     style="background-color: ${lesson.color_code || '#0b6cf5'}"
                                     data-lesson-id="${lesson.id}"
                                     title="${lesson.teacher_name || lesson.student_name} - ${this.formatTime(lesson.start_time)}">
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            html += `
                    </div>
                </div>
            `;
            
            this.container.innerHTML = html;
            this.attachEventListeners();
        }
        
        renderWeekView() {
            // Week view implementation
            const startOfWeek = this.getStartOfWeek(this.currentDate);
            let html = `
                <div class="calendar-header">
                    <button class="calendar-nav-btn" onclick="calendar.prevWeek()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h2>Week of ${startOfWeek.toLocaleDateString()}</h2>
                    <button class="calendar-nav-btn" onclick="calendar.nextWeek()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="calendar-grid week-view">
                    <div class="calendar-time-column">
                        ${this.generateTimeSlots().map(time => `
                            <div class="time-slot-header">${time}</div>
                        `).join('')}
                    </div>
                    <div class="calendar-days-columns">
                        ${this.getWeekDays(startOfWeek).map(day => `
                            <div class="calendar-day-column" data-date="${this.formatDate(day)}">
                                <div class="day-header">
                                    <div class="day-name">${day.toLocaleDateString('en-US', { weekday: 'short' })}</div>
                                    <div class="day-number">${day.getDate()}</div>
                                </div>
                                <div class="day-time-slots">
                                    ${this.generateTimeSlots().map(time => `
                                        <div class="time-slot" data-time="${time}" data-date="${this.formatDate(day)}">
                                            ${this.renderSlotContent(day, time)}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            this.container.innerHTML = html;
            this.attachEventListeners();
        }
        
        renderDayView() {
            const dateStr = this.formatDate(this.currentDate);
            let html = `
                <div class="calendar-header">
                    <button class="calendar-nav-btn" onclick="calendar.prevDay()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h2>${this.currentDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}</h2>
                    <button class="calendar-nav-btn" onclick="calendar.nextDay()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="calendar-grid day-view">
                    <div class="calendar-time-column">
                        ${this.generateTimeSlots().map(time => `
                            <div class="time-slot-header">${time}</div>
                        `).join('')}
                    </div>
                    <div class="calendar-day-column" data-date="${dateStr}">
                        ${this.generateTimeSlots().map(time => `
                            <div class="time-slot" data-time="${time}" data-date="${dateStr}">
                                ${this.renderSlotContent(this.currentDate, time)}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            this.container.innerHTML = html;
            this.attachEventListeners();
        }
        
        renderSlotContent(date, time) {
            const dateStr = this.formatDate(date);
            const lessons = this.getLessonsForDateTime(dateStr, time);
            const isAvailable = this.isSlotAvailable(dateStr, time);
            
            if (lessons.length > 0) {
                return lessons.map(lesson => `
                    <div class="lesson-block" 
                         style="background-color: ${lesson.color_code || '#0b6cf5'}"
                         data-lesson-id="${lesson.id}">
                        ${lesson.teacher_name || lesson.student_name} - ${this.formatTime(lesson.start_time)}
                    </div>
                `).join('');
            } else if (isAvailable) {
                return '<div class="available-slot">Available</div>';
            } else {
                return '';
            }
        }
        
        generateTimeSlots() {
            const slots = [];
            for (let hour = 0; hour < 24; hour++) {
                slots.push(String(hour).padStart(2, '0') + ':00');
                slots.push(String(hour).padStart(2, '0') + ':30');
            }
            return slots;
        }
        
        getWeekDays(startDate) {
            const days = [];
            for (let i = 0; i < 7; i++) {
                const date = new Date(startDate);
                date.setDate(date.getDate() + i);
                days.push(date);
            }
            return days;
        }
        
        getStartOfWeek(date) {
            const d = new Date(date);
            const day = d.getDay();
            const diff = d.getDate() - day;
            return new Date(d.setDate(diff));
        }
        
        getLessonsForDate(dateStr) {
            return this.lessons.filter(lesson => lesson.lesson_date === dateStr);
        }
        
        getLessonsForDateTime(dateStr, time) {
            return this.lessons.filter(lesson => {
                return lesson.lesson_date === dateStr && 
                       lesson.start_time.substring(0, 5) === time;
            });
        }
        
        isSlotAvailable(dateStr, time) {
            if (!this.options.teacherId) return false;
            return this.availability.some(slot => {
                return slot.date === dateStr && 
                       slot.start_time <= time && 
                       slot.end_time > time;
            });
        }
        
        isToday(date) {
            const today = new Date();
            return date.getDate() === today.getDate() &&
                   date.getMonth() === today.getMonth() &&
                   date.getFullYear() === today.getFullYear();
        }
        
        formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        formatTime(timeStr) {
            if (!timeStr) return '';
            const [hours, minutes] = timeStr.split(':');
            const date = new Date();
            date.setHours(parseInt(hours), parseInt(minutes));
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }
        
        prevMonth() {
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.render();
            this.loadLessons();
        }
        
        nextMonth() {
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.render();
            this.loadLessons();
        }
        
        prevWeek() {
            this.currentDate.setDate(this.currentDate.getDate() - 7);
            this.render();
            this.loadLessons();
        }
        
        nextWeek() {
            this.currentDate.setDate(this.currentDate.getDate() + 7);
            this.render();
            this.loadLessons();
        }
        
        prevDay() {
            this.currentDate.setDate(this.currentDate.getDate() - 1);
            this.render();
            this.loadLessons();
        }
        
        nextDay() {
            this.currentDate.setDate(this.currentDate.getDate() + 1);
            this.render();
            this.loadLessons();
        }
        
        attachEventListeners() {
            // Lesson click handlers
            this.container.querySelectorAll('.lesson-indicator, .lesson-block').forEach(el => {
                el.addEventListener('click', (e) => {
                    const lessonId = e.currentTarget.dataset.lessonId;
                    const lesson = this.lessons.find(l => l.id == lessonId);
                    if (lesson && this.options.onLessonClick) {
                        this.options.onLessonClick(lesson);
                    }
                });
            });
            
            // Slot selection handlers
            this.container.querySelectorAll('.available-slot, .time-slot').forEach(el => {
                el.addEventListener('click', (e) => {
                    const date = e.currentTarget.dataset.date || 
                                e.currentTarget.closest('.calendar-day')?.dataset.date;
                    const time = e.currentTarget.dataset.time;
                    
                    if (date && time && this.options.onSlotSelect) {
                        this.options.onSlotSelect({ date, time });
                    }
                });
            });
        }
        
        async loadLessons() {
            if (!this.options.teacherId && !this.options.studentId) return;
            
            const dateFrom = this.getDateRange().start;
            const dateTo = this.getDateRange().end;
            const role = this.options.teacherId ? 'teacher' : 'student';
            const userId = this.options.teacherId || this.options.studentId;
            
            try {
                const response = await fetch(
                    `/api/calendar/lessons?user_id=${userId}&timezone=${this.options.timezone}&date_from=${dateFrom}&date_to=${dateTo}&role=${role}`
                );
                const data = await response.json();
                if (data.success && data.lessons) {
                    this.lessons = data.lessons;
                    this.render();
                }
            } catch (error) {
                console.error('Failed to load lessons:', error);
            }
        }
        
        async loadAvailability() {
            if (!this.options.teacherId) return;
            
            const dateFrom = this.getDateRange().start;
            const dateTo = this.getDateRange().end;
            
            try {
                const response = await fetch(
                    `/api/calendar/availability?teacher_id=${this.options.teacherId}&timezone=${this.options.timezone}&date_from=${dateFrom}&date_to=${dateTo}`
                );
                const data = await response.json();
                if (data.success && data.slots) {
                    this.availability = data.slots;
                    this.render();
                }
            } catch (error) {
                console.error('Failed to load availability:', error);
            }
        }
        
        getDateRange() {
            const view = this.options.view;
            let start, end;
            
            if (view === 'month') {
                const year = this.currentDate.getFullYear();
                const month = this.currentDate.getMonth();
                start = new Date(year, month, 1);
                end = new Date(year, month + 1, 0);
            } else if (view === 'week') {
                start = this.getStartOfWeek(this.currentDate);
                end = new Date(start);
                end.setDate(end.getDate() + 6);
            } else {
                start = new Date(this.currentDate);
                end = new Date(this.currentDate);
            }
            
            return {
                start: this.formatDate(start),
                end: this.formatDate(end)
            };
        }
        
        setLessons(lessons) {
            this.lessons = lessons;
            this.render();
        }
        
        setAvailability(availability) {
            this.availability = availability;
            this.render();
        }
    }
    
    // Export to window
    window.Calendar = Calendar;
})();


