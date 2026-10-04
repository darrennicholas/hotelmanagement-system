'use client';

import { FormEvent, useState } from 'react';
import styles from '@/app/page.module.css';
import { api } from '@/services/api';
import type { User } from '@/types';

type AuthMode = 'login' | 'register';

export default function Login({ onLogin }: { onLogin: (user: User) => void }) {
  const [mode, setMode] = useState<AuthMode>('login');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('admin@hotel.test');
  const [password, setPassword] = useState('password');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  function switchMode(nextMode: AuthMode) {
    setMode(nextMode);
    setError('');
    setNotice('');
    setShowPassword(false);
    if (nextMode === 'register') {
      setPassword('');
      setPasswordConfirmation('');
    } else {
      setPassword('password');
    }
  }

  async function submit(event: FormEvent) {
    event.preventDefault();
    setError('');
    setNotice('');

    if (mode === 'register' && password !== passwordConfirmation) {
      setError('Konfirmasi kata sandi belum sama.');
      return;
    }

    setLoading(true);
    try {
      if (mode === 'login') {
        const result = await api<{ token: string; user: User }>('/login', {
          method: 'POST',
          body: JSON.stringify({ email, password }),
        });
        localStorage.setItem('hotel_token', result.token);
        onLogin(result.user);
      } else {
        await api('/register', {
          method: 'POST',
          body: JSON.stringify({ name, email, password, password_confirmation: passwordConfirmation }),
        });
        setMode('login');
        setPassword('');
        setPasswordConfirmation('');
        setNotice('Akun berhasil dibuat. Silakan masuk.');
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Terjadi kesalahan.');
    } finally {
      setLoading(false);
    }
  }

  const isRegistering = mode === 'register';

  return <main className={styles.loginShell}>
    <section className={styles.loginCard}>
      <div className={styles.loginBrand}>
        <div className={styles.brandMark}>H</div>
        <div><strong>Haven</strong><span>SISTEM HOTEL</span></div>
      </div>

      <div className={styles.loginCardHeader}>
        <p className={styles.eyebrow}>{isRegistering ? 'BUAT AKUN PENGGUNA' : 'RUANG KERJA HOTEL'}</p>
        <h1>{isRegistering ? 'Buat akun baru' : 'Selamat datang kembali'}</h1>
        <p>{isRegistering ? 'Daftarkan akun untuk mulai mengelola operasional hotel.' : 'Masuk untuk melanjutkan pekerjaan operasional hari ini.'}</p>
      </div>

      <form onSubmit={submit} className={styles.form}>
        {isRegistering && <label>
          <span>Nama lengkap</span>
          <div className={styles.inputShell}>
            <input aria-label="Nama lengkap" autoComplete="name" type="text" value={name} onChange={(event) => setName(event.target.value)} required />
          </div>
        </label>}
        <label>
          <span>Email</span>
          <div className={styles.inputShell}>
            <input aria-label="Email" autoComplete="email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
          </div>
        </label>
        <label>
          <span>Kata sandi</span>
          <div className={styles.inputShell}>
            <input aria-label="Kata sandi" autoComplete={isRegistering ? 'new-password' : 'current-password'} type={showPassword ? 'text' : 'password'} value={password} onChange={(event) => setPassword(event.target.value)} minLength={8} required />
            <button type="button" className={styles.passwordToggle} onClick={() => setShowPassword((value) => !value)} aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}>{showPassword ? 'Sembunyikan' : 'Tampilkan'}</button>
          </div>
        </label>
        {isRegistering && <label>
          <span>Ulangi kata sandi</span>
          <div className={styles.inputShell}>
            <input aria-label="Ulangi kata sandi" autoComplete="new-password" type={showPassword ? 'text' : 'password'} value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} minLength={8} required />
          </div>
        </label>}
        {notice && <p className={styles.success}>{notice}</p>}
        {error && <p className={styles.error}>{error}</p>}
        <button type="submit" className={styles.primaryButton} disabled={loading}>
          {loading ? 'Memproses...' : isRegistering ? 'Buat akun' : 'Masuk'}
        </button>
      </form>

      <p className={styles.loginHint}>
        {isRegistering ? 'Sudah punya akun?' : 'Belum punya akun?'}{' '}
        <button type="button" className={styles.authLink} onClick={() => switchMode(isRegistering ? 'login' : 'register')}>
          {isRegistering ? 'Kembali ke login' : 'Daftar sebagai pengguna'}
        </button>
      </p>
    </section>

    <section className={styles.loginVisual} aria-label="Ringkasan Haven Hotel System">
      <div className={styles.visualOverlay}>
        <p className={styles.visualKicker}>HAVEN HOTEL SYSTEM</p>
        <h2>Semua yang penting, terlihat jelas.</h2>
        <p>Kelola reservasi, kamar, tamu, dan pembayaran dari satu tempat.</p>
      </div>
    </section>
  </main>;
}
