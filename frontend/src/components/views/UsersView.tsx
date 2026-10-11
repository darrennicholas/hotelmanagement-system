'use client';

import { FormEvent, useState } from 'react';
import styles from '@/app/page.module.css';
import { api } from '@/services/api';
import { confirmAction, successAlert } from '@/services/alerts';
import type { Paginated, Role, User } from '@/types';
import { Empty, ErrorNotice, Panel, Pagination, Status } from '@/components/ui';

export default function UsersView({ users, roles, onSaved, pagination, onPageChange }: { users: User[]; roles: Role[]; onSaved: () => void; pagination?: Paginated<User>; onPageChange?: (page: number) => void }) {
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ name: '', email: '', password: '', role_id: '' });

  async function save(event: FormEvent) {
    event.preventDefault();
    if (!await confirmAction('Tambah user?', `${form.name || 'User baru'} akan dibuat dengan role yang dipilih.`)) return;

    setError(''); setSaving(true);
    try {
      await api('/users', { method: 'POST', body: JSON.stringify({ ...form, role_id: Number(form.role_id) }) });
      setShowForm(false);
      setForm({ name: '', email: '', password: '', role_id: '' });
      await successAlert('User berhasil ditambahkan', 'Akun user sudah tersimpan.');
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'User gagal disimpan.');
    } finally { setSaving(false); }
  }

  async function toggle(user: User) {
    if (!await confirmAction(`${user.status ? 'Nonaktifkan' : 'Aktifkan'} user?`, `Status akun ${user.name} akan diubah.`)) return;

    setError(''); setSaving(true);
    try {
      await api(`/users/${user.id}`, { method: 'PUT', body: JSON.stringify({ status: !user.status }) });
      await successAlert('Status user diperbarui', `${user.name} sekarang ${user.status ? 'nonaktif' : 'aktif'}.`);
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Status user gagal diubah.');
    } finally { setSaving(false); }
  }

  return <Panel title="Users" action={<button className={styles.secondaryButton} onClick={() => setShowForm(!showForm)}>{showForm ? 'Tutup form' : '+ Add user'}</button>}>
    {error && <ErrorNotice message={error} />}
    {showForm && <form className={styles.inlineForm} onSubmit={save}>
      <input required placeholder="Nama" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />
      <input required type="email" placeholder="Email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
      <input required minLength={8} type="password" placeholder="Password min. 8 karakter" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} />
      <select required value={form.role_id} onChange={(event) => setForm({ ...form, role_id: event.target.value })}><option value="">Pilih role</option>{roles.map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}</select>
      <button className={styles.primaryButton} disabled={saving}>{saving ? 'Menyimpan...' : 'Simpan'}</button>
    </form>}
    <div className={styles.table}>
      <div className={styles.tableHead}><span>NAME</span><span>EMAIL</span><span>ROLE</span><span>STATUS</span><span>MEMBER</span><span>ACTION</span></div>
      {users.map((user) => <div className={styles.tableRow} key={user.id}>
        <strong>{user.name}</strong>
        <span>{user.email}</span>
        <span>{user.role?.name ?? '-'}</span>
        <span><Status value={user.status ? 'Active' : 'Inactive'} /></span>
        <span>{user.role?.name === 'User' ? <Status value="Active" label={`Member ${Number(user.membership?.discount_rate ?? 10)}% · min. 2 tamu`} /> : '-'}</span>
        <span>
          <button disabled={saving} className={styles.rowActionButton} onClick={() => toggle(user)}>{user.status ? 'Nonaktifkan' : 'Aktifkan'}</button>
        </span>
      </div>)}
    </div>
    {pagination && onPageChange && <Pagination currentPage={pagination.current_page} lastPage={pagination.last_page} total={pagination.total} onPageChange={onPageChange} />}
    {!users.length && <Empty text="Belum ada user." />}
  </Panel>;
}
