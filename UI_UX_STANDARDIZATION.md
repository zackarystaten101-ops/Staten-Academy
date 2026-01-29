# UI/UX Standardization Document
## Staten Academy Platform

### Color Scheme
- **Primary Blue**: `#0b6cf5` / `#004080`
- **Success Green**: `#28a745`
- **Warning Yellow**: `#ffc107`
- **Danger Red**: `#dc3545`
- **Info Cyan**: `#17a2b8`
- **Gray Text**: `#666` / `var(--gray)`
- **Light Background**: `#f8f9fa` / `var(--light-gray)`

### Typography
- **Headings**: Bold, color `#333` or `#004080`
- **Body Text**: Regular, color `#666` or `var(--gray)`
- **Font Family**: System fonts (inherit from body)

### Button Styles
- **Primary Button**: 
  - Background: `#0b6cf5` or `#004080`
  - Color: White
  - Padding: `10px 20px`
  - Border-radius: `5px` or `8px`
  - Hover: Darker shade, slight transform

- **Outline Button**:
  - Background: Transparent
  - Border: `2px solid #0b6cf5`
  - Color: `#0b6cf5`
  - Same padding and border-radius

- **Secondary Button**:
  - Background: `#f0f0f0`
  - Color: `#333`
  - Same padding and border-radius

### Card Components
- Background: White
- Padding: `20px` to `30px`
- Border-radius: `8px` to `12px`
- Box-shadow: `0 2px 8px rgba(0,0,0,0.1)`
- Margin-bottom: `20px` to `30px`

### Form Elements
- Input padding: `10px` to `12px`
- Border: `1px solid #ddd`
- Border-radius: `5px`
- Focus: Border color `#0b6cf5`, outline none

### Spacing
- Section margin: `30px`
- Card gap: `20px`
- Element gap: `15px`
- Small gap: `10px`

### Icons
- Font Awesome 6.0.0
- Size: `1rem` to `1.5rem` for inline
- Color: Match text or use brand colors

### Responsive Breakpoints
- Mobile: `320px - 768px`
- Tablet: `768px - 1024px`
- Desktop: `1024px+`

### Navigation
- Sidebar width: `250px` (desktop)
- Mobile: Hamburger menu, overlay
- Active state: Background `#f0f7ff`, border-left `4px solid #0b6cf5`

### Status Indicators
- **Success**: Green badge/tag
- **Warning**: Yellow badge/tag
- **Danger**: Red badge/tag
- **Info**: Blue badge/tag

### Loading States
- Spinner: Font Awesome `fa-spinner fa-spin`
- Color: `#0b6cf5`
- Size: `1.5rem` to `2rem`

### Empty States
- Icon: Large, color `#ddd`
- Text: Centered, color `#666`
- Call-to-action: Primary button

### Modals
- Background overlay: `rgba(0,0,0,0.5)`
- Modal content: White, `border-radius: 12px`, `padding: 30px`
- Max-width: `500px` to `600px`
- Centered with flexbox

### Accessibility
- All buttons have `aria-label` where needed
- Form inputs have proper labels
- Color contrast meets WCAG AA standards
- Keyboard navigation supported

### Animation
- Transitions: `0.3s` ease
- Hover effects: `transform: translateY(-2px)`
- Loading: `fa-spin` animation
