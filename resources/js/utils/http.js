/** Respons 401/419 dari fetch: sesi berakhir atau akun dinonaktifkan. Kembali ke halaman login. */
export const sessionEnded = (res) => {
    if (res.status !== 401 && res.status !== 419) return false;
    window.location.assign('/login');
    return true;
};
