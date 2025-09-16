// PM2 설정 파일 - 창녕조씨 족보 시스템
module.exports = {
  apps: [
    {
      name: 'cyjc-family-tree',
      script: 'npx',
      args: 'wrangler pages dev dist --ip 0.0.0.0 --port 3000',
      env: {
        NODE_ENV: 'development',
        PORT: 3000,
        // 카페24 DB 연결용 환경변수 (실제 값은 .env 파일에서)
        DB_HOST: 'localhost',
        DB_USER: 'root',
        DB_PASSWORD: '',
        DB_NAME: 'cyjc_family'
      },
      watch: false, // 파일 변경 감시 비활성화 (wrangler가 핫리로드 담당)
      instances: 1, // 개발 환경에서는 단일 인스턴스
      exec_mode: 'fork',
      log_file: './logs/combined.log',
      out_file: './logs/out.log',
      error_file: './logs/error.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      // 자동 재시작 설정
      autorestart: true,
      max_restarts: 10,
      min_uptime: '10s',
      // 메모리 제한
      max_memory_restart: '500M'
    }
  ]
}