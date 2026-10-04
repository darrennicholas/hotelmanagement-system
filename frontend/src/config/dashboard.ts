import type { Dashboard } from '@/types';

export const emptyDashboard: Dashboard = {
  rooms: { total: 0, available: 0, occupied: 0, reserved: 0, maintenance: 0 },
  reservations: { today: 0, check_ins: 0, check_outs: 0, cancelled: 0 },
  financial: { today: 0, month: 0 },
  occupancy_rate: 0,
};

export const menu = [
  { label: 'Overview', icon: '⌂', group: 'WORKSPACE' },
  { label: 'Reservations', icon: '▣', group: 'WORKSPACE' },
  { label: 'Rooms', icon: '▤', group: 'WORKSPACE' },
  { label: 'Check-in / out', icon: '◈', group: 'WORKSPACE' },
  { label: 'Payments', icon: '◇', group: 'MANAGEMENT' },
  { label: 'Reports', icon: '▥', group: 'MANAGEMENT' },
  { label: 'Services', icon: '✦', group: 'MANAGEMENT' },
  { label: 'Facilities', icon: '◇', group: 'MANAGEMENT' },
  { label: 'Users', icon: '♧', group: 'MANAGEMENT' },
];
