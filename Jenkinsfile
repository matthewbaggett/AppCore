pipeline {
    agent any
    stages {
        stage('Setup') {
            steps {
                sh 'make clean'
                sh 'make setup'
            }
        }
        stage('Test') {
            steps {
                sh 'make test'
            }
        }
    }
    post {
        always {
            sh 'make clean'
            cleanWs()
        }
    }
}