module.exports = {
  apps: [
    {
      name: 'cyjc-genealogy',
      script: 'php',
      args: '-S 0.0.0.0:3000 -t .',
      cwd: '/home/user/webapp',
      env: {
        NODE_ENV: 'development',
        PORT: 3000
      },
      watch: false,
      instances: 1,
      exec_mode: 'fork',
      error_file: './logs/err.log',
      out_file: './logs/out.log',
      log_file: './logs/combined.log',
      time: true
    }
  ]
}