<?php

return [
    'auth.register.success' => 'Registro exitoso',
    'auth.register.email_exists' => 'El correo ya está registrado',
    'auth.register.failed' => 'El registro falló',

    'auth.login.success' => 'Inicio de sesión exitoso',
    'auth.login.failed' => 'Credenciales inválidas',

    'auth.logout.success' => 'Cierre de sesión exitoso',

    'auth.refresh.success' => 'Token actualizado correctamente',
    'auth.refresh.failed' => 'No se pudo actualizar el token',

    'auth.password.reset.success' => 'Restablecimiento de contraseña exitoso',
    'auth.password.reset.failed' => 'No se pudo restablecer la contraseña',

    'auth.not_authenticated' => 'No autenticado',

    'otp.sent' => 'OTP enviado correctamente',
    'otp.send_failed' => 'No se pudo enviar el OTP',
    'otp.verified' => 'OTP verificado correctamente',
    'otp.invalid' => 'OTP inválido',
    'otp.verify_failed' => 'No se pudo verificar el OTP',

    'otp.admin.history.failed' => 'No se pudo obtener el historial de OTP',
    'otp.admin.statistics.failed' => 'No se pudieron obtener las estadísticas de OTP',
    'otp.admin.blacklist.failed' => 'No se pudo obtener la lista negra de OTP',
    'otp.admin.blacklist.reason.default' => 'Bloqueado por el administrador',
    'otp.admin.blacklist.identifier_required' => 'Se requieren el tipo y el valor del identificador',
    'otp.admin.blacklist.added' => 'Identificador agregado a la lista negra correctamente',
    'otp.admin.blacklist.add_failed' => 'No se pudo agregar el identificador a la lista negra',
    'otp.admin.blacklist.removed' => 'Identificador eliminado de la lista negra',
    'otp.admin.blacklist.remove_failed' => 'No se pudo eliminar el identificador de la lista negra',
    'otp.admin.status.identifier_required' => 'Se requiere el identificador',
    'otp.admin.status.failed' => 'No se pudo comprobar el estado del OTP',
    'otp.admin.cleanup.success' => 'Limpieza de OTP completada',
    'otp.admin.cleanup.failed' => 'No se pudo limpiar la data de OTP',
];
