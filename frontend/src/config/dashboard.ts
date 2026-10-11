import type { Dashboard } from '@/types';

export const emptyDashboard: Dashboard = {
  rooms: { total: 0, available: 0, occupied: 0, reserved: 0, maintenance: 0 },
  reservations: { today: 0, check_ins: 0, check_outs: 0, cancelled: 0 },
  financial: { today: 0, month: 0 },
  occupancy_rate: 0,
};

export const menu = [
  { label: 'Overview', icon: 'dashboard', group: 'WORKSPACE' },
  { label: 'Reservations', icon: 'calendar', group: 'WORKSPACE' },
  { label: 'Rooms', icon: 'room', group: 'WORKSPACE' },
  { label: 'Check-in / out', icon: 'check', group: 'WORKSPACE' },
  { label: 'Payments', icon: 'payment', group: 'MANAGEMENT' },
  { label: 'Reports', icon: 'report', group: 'MANAGEMENT' },
  { label: 'Services', icon: 'service', group: 'MANAGEMENT' },
  { label: 'Facilities', icon: 'facility', group: 'MANAGEMENT' },
  { label: 'Users', icon: 'user', group: 'MANAGEMENT' },
] as const;
