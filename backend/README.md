# Staten Academy Backend API

Node/Express backend for Wallet, Calendar, and Earnings system.

## Setup

1. Install dependencies:
```bash
npm install
```

2. Configure environment:
```bash
cp .env.example .env
# Edit .env with your PostgreSQL and MySQL credentials
```

3. Run database migrations:
```bash
npm run migrate
```

4. Sync data from MySQL (optional):
```bash
tsx src/migrations/sync-mysql-to-postgres.ts
```

5. Start development server:
```bash
npm run dev
```

## API Endpoints

### Wallet
- `GET /api/wallets/:userId` - Get student entitlements
- `GET /api/wallets/:userId/ledger` - Get transaction history

### Bookings
- `POST /api/bookings/slots/request` - Request a slot
- `POST /api/bookings/slots/:id/accept` - Accept slot request
- `POST /api/bookings/slots/:id/decline` - Decline slot request
- `GET /api/bookings/classes` - Get classes
- `POST /api/bookings/classes/:id/cancel` - Cancel class

### Calendar
- `GET /api/calendar` - Get unified calendar events
- `GET /api/calendar/availability/:teacherId` - Get teacher availability

### Earnings (Teacher/Admin Only)
- `GET /api/earnings` - Get earnings
- `GET /api/earnings/summary` - Get earnings summary
- `POST /api/earnings/:id/mark-paid` - Mark earnings as paid

### Recurring
- `POST /api/recurring` - Create recurring series
- `POST /api/recurring/:id/generate` - Generate future classes
- `POST /api/recurring/:id/pause` - Pause series
- `POST /api/recurring/:id/resume` - Resume series

### Admin
- `POST /api/admin/slots/add` - Add availability slot
- `POST /api/admin/bookings/force` - Force-book class
- `PATCH /api/admin/teachers/:id/rate` - Set teacher rate
- `GET /api/admin/audit-logs` - Get audit logs
- `POST /api/admin/payroll/export` - Export payroll CSV

## Security

- All endpoints require authentication (JWT token)
- Students are BLOCKED from earnings endpoints
- Response sanitization middleware removes earnings data from student responses
- Server-side RBAC enforcement

## Database

- PostgreSQL for new features
- MySQL sync for existing data (optional)
- All times stored in UTC








