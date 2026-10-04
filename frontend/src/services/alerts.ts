import Swal from 'sweetalert2';

export async function confirmAction(title: string, text: string, confirmButtonText = 'Ya, lanjutkan') {
  const result = await Swal.fire({
    title,
    text,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText,
    cancelButtonText: 'Batal',
    reverseButtons: true,
    focusCancel: true,
    customClass: { confirmButton: 'alertConfirm', cancelButton: 'alertCancel' },
  });
  return result.isConfirmed;
}

export function successAlert(title: string, text: string) {
  return Swal.fire({
    title,
    text,
    icon: 'success',
    timer: 1800,
    timerProgressBar: true,
    showConfirmButton: false,
    customClass: { popup: 'successAlert' },
  });
}
