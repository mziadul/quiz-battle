pipeline {

    agent any



    environment {

        IMAGE_NAME = "quiz-battle-app"

        IMAGE_TAG  = "${env.BUILD_NUMBER}"

    }



    stages {

        stage('1. Checkout Code') {

            steps {

                echo 'GitHub থেকে লেটেস্ট কোড আনা হচ্ছে...'

                checkout scm

            }

        }



        stage('2. Build Docker Image') {

            steps {

                echo 'নতুন Docker Image বিল্ড করা হচ্ছে...'

                sh "docker build -t ${IMAGE_NAME}:${IMAGE_TAG} -t ${IMAGE_NAME}:latest ."

            }

        }



        stage('3. Run Automated Tests') {
            steps {
                echo 'কন্টেইনারের ভেতরে Laravel টেস্ট চালানো হচ্ছে...'
                sh "docker run --rm -e APP_ENV=testing -e APP_KEY=base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10= ${IMAGE_NAME}:${IMAGE_TAG} php artisan test"
            }
        }



        stage('4. Deploy Application') {

            steps {

                echo 'Production প্রোফাইলে অ্যাপ ডিপ্লয় করা হচ্ছে...'

                sh "docker compose --profile prod up -d --build"

            }

        }

    }



    post {

        always {

            sh "docker image prune -f"

        }

        success {

            echo "অভিনন্দন! Build #${env.BUILD_NUMBER} সফলভাবে ডিপ্লয় হয়েছে।"

        }

        failure {

            echo "পাইপলাইন ফেইল করেছে! Console Output চেক করুন।"

        }

    }

}